<?php

use App\Actions\Categories\DeleteCategory;
use App\Actions\Workspaces\CreateWorkspace;
use App\Models\Account;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Validation\ValidationException;
use Inertia\Testing\AssertableInertia;

it('seeds default categories on workspace create', function () {
    $user = User::factory()->create();
    $workspace = (new CreateWorkspace)->execute($user, 'Семья', 'RUB');

    expect($workspace->categories()->count())->toBeGreaterThanOrEqual(10);
});

it('refuses to delete a category that has transactions', function () {
    $user = User::factory()->create();
    $workspace = (new CreateWorkspace)->execute($user, 'Семья', 'RUB');
    $category = $workspace->categories()->where('kind', 'expense')->first();
    $account = Account::factory()->create(['workspace_id' => $workspace->id]);
    Transaction::factory()->create([
        'workspace_id' => $workspace->id,
        'account_id' => $account->id,
        'category_id' => $category->id,
        'type' => 'expense',
        'amount' => 100,
    ]);

    (new DeleteCategory)->execute($category);
})->throws(ValidationException::class);

it('creates a category via http', function () {
    $user = User::factory()->create();
    (new CreateWorkspace)->execute($user, 'Семья', 'RUB');

    $this->actingAs($user)
        ->from(route('categories.index'))
        ->post(route('categories.store'), [
            'kind' => 'expense',
            'name' => 'Питомцы',
            'emoji' => '🐾',
            'color' => '#1C6CFF',
        ])
        ->assertRedirect(route('categories.index'));

    $this->assertDatabaseHas('categories', [
        'name' => 'Питомцы',
        'kind' => 'expense',
        'emoji' => '🐾',
        'color' => '#1C6CFF',
    ]);
});

it('lists categories grouped by kind', function () {
    $user = User::factory()->create();
    (new CreateWorkspace)->execute($user, 'Семья', 'RUB');

    $this->withoutVite()
        ->actingAs($user)
        ->get(route('categories.index'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('categories/Index')
            ->has('expense', 7)
            ->has('income', 3)
            ->where('expense.0.name', 'Жильё')
            ->where('income.0.name', 'Зарплата')
        );
});
