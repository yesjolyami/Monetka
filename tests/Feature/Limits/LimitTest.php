<?php

use App\Actions\Accounts\CreateAccount;
use App\Actions\Limits\DeleteCategoryLimit;
use App\Actions\Limits\UpsertCategoryLimit;
use App\Actions\Transactions\RecordExpense;
use App\Actions\Workspaces\CreateWorkspace;
use App\Models\CategoryLimit;
use App\Models\User;
use App\Support\LimitStatus;
use Illuminate\Validation\ValidationException;
use Inertia\Testing\AssertableInertia;

it('flags exceeded but still records the expense', function () {
    $user = User::factory()->create();
    $ws = (new CreateWorkspace)->execute($user, 'Семья', 'RUB');
    $account = (new CreateAccount)->execute($ws, $user, ['name' => 'A', 'type' => 'checking', 'bank_id' => null, 'user_id' => null, 'opening_balance' => 0]);
    $category = $ws->categories()->where('kind', 'expense')->first();
    $limit = (new UpsertCategoryLimit)->execute($ws, $category, 1000);
    (new RecordExpense)->execute($ws, $user, [
        'account_id' => $account->id, 'category_id' => $category->id, 'amount' => 1500,
        'occurred_on' => '2026-09-05', 'description' => 'много',
    ]);
    $status = LimitStatus::for($limit, 2026, 9);
    expect($status['exceeded'])->toBeTrue()->and($status['spent'])->toBe(1500);
});

it('updates the unique workspace category limit amount', function () {
    $user = User::factory()->create();
    $ws = (new CreateWorkspace)->execute($user, 'Семья', 'RUB');
    $category = $ws->categories()->where('kind', 'expense')->first();

    $first = (new UpsertCategoryLimit)->execute($ws, $category, 1000);
    $second = (new UpsertCategoryLimit)->execute($ws, $category, 2500);

    expect($second->id)->toBe($first->id)
        ->and($second->amount)->toBe(2500)
        ->and(CategoryLimit::query()->where('workspace_id', $ws->id)->where('category_id', $category->id)->count())->toBe(1);
});

it('refuses a limit on an income category', function () {
    $user = User::factory()->create();
    $ws = (new CreateWorkspace)->execute($user, 'Семья', 'RUB');
    $category = $ws->categories()->where('kind', 'income')->first();

    (new UpsertCategoryLimit)->execute($ws, $category, 1000);
})->throws(ValidationException::class);

it('refuses a limit on a category from another workspace', function () {
    $a = User::factory()->create();
    $b = User::factory()->create();
    $wsA = (new CreateWorkspace)->execute($a, 'A', 'RUB');
    $wsB = (new CreateWorkspace)->execute($b, 'B', 'RUB');
    $categoryB = $wsB->categories()->where('kind', 'expense')->first();

    (new UpsertCategoryLimit)->execute($wsA, $categoryB, 1000);
})->throws(ValidationException::class);

it('counts only expenses in the requested calendar month', function () {
    $user = User::factory()->create();
    $ws = (new CreateWorkspace)->execute($user, 'Семья', 'RUB');
    $account = (new CreateAccount)->execute($ws, $user, ['name' => 'A', 'type' => 'checking', 'bank_id' => null, 'user_id' => null, 'opening_balance' => 0]);
    $category = $ws->categories()->where('kind', 'expense')->first();
    $limit = (new UpsertCategoryLimit)->execute($ws, $category, 10000);

    (new RecordExpense)->execute($ws, $user, [
        'account_id' => $account->id, 'category_id' => $category->id, 'amount' => 400,
        'occurred_on' => '2026-08-31', 'description' => 'август',
    ]);
    (new RecordExpense)->execute($ws, $user, [
        'account_id' => $account->id, 'category_id' => $category->id, 'amount' => 700,
        'occurred_on' => '2026-09-01', 'description' => 'сентябрь',
    ]);
    (new RecordExpense)->execute($ws, $user, [
        'account_id' => $account->id, 'category_id' => $category->id, 'amount' => 300,
        'occurred_on' => '2026-10-01', 'description' => 'октябрь',
    ]);

    $status = LimitStatus::for($limit, 2026, 9);

    expect($status['spent'])->toBe(700)
        ->and($status['exceeded'])->toBeFalse()
        ->and($status['amount'])->toBe(10000);
});

it('does not flag a limit that is exactly spent', function () {
    $user = User::factory()->create();
    $ws = (new CreateWorkspace)->execute($user, 'Семья', 'RUB');
    $account = (new CreateAccount)->execute($ws, $user, ['name' => 'A', 'type' => 'checking', 'bank_id' => null, 'user_id' => null, 'opening_balance' => 0]);
    $category = $ws->categories()->where('kind', 'expense')->first();
    $limit = (new UpsertCategoryLimit)->execute($ws, $category, 1500);

    (new RecordExpense)->execute($ws, $user, [
        'account_id' => $account->id, 'category_id' => $category->id, 'amount' => 1500,
        'occurred_on' => '2026-09-05', 'description' => 'ровно',
    ]);

    $status = LimitStatus::for($limit, 2026, 9);

    expect($status['exceeded'])->toBeFalse()->and($status['spent'])->toBe(1500);
});

it('deletes a category limit', function () {
    $user = User::factory()->create();
    $ws = (new CreateWorkspace)->execute($user, 'Семья', 'RUB');
    $category = $ws->categories()->where('kind', 'expense')->first();
    $limit = (new UpsertCategoryLimit)->execute($ws, $category, 1000);

    (new DeleteCategoryLimit)->execute($limit);

    expect(CategoryLimit::query()->find($limit->id))->toBeNull();
});

it('creates a limit via http and lists spent as exceeded', function () {
    $user = User::factory()->create();
    $ws = (new CreateWorkspace)->execute($user, 'Семья', 'RUB');
    $account = (new CreateAccount)->execute($ws, $user, [
        'name' => 'A',
        'type' => 'checking',
        'bank_id' => null,
        'user_id' => null,
        'opening_balance' => 0,
    ]);
    $category = $ws->categories()->where('kind', 'expense')->first();

    $this->actingAs($user)
        ->from(route('limits.index'))
        ->post(route('limits.store'), [
            'category_id' => $category->id,
            'amount' => '10.00',
        ])
        ->assertRedirect(route('limits.index'));

    $limit = $ws->categoryLimits()->where('category_id', $category->id)->firstOrFail();

    expect($limit->amount)->toBe(1000);

    (new RecordExpense)->execute($ws, $user, [
        'account_id' => $account->id,
        'category_id' => $category->id,
        'amount' => 1500,
        'occurred_on' => now()->toDateString(),
        'description' => 'много',
    ]);

    $this->actingAs($user)
        ->from(route('limits.index'))
        ->patch(route('limits.update', $limit), [
            'amount' => '12.00',
        ])
        ->assertRedirect(route('limits.index'))
        ->assertSessionHasNoErrors();

    expect($limit->fresh()->amount)->toBe(1200);

    $this->withoutVite()
        ->get(route('limits.index'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('limits/Index')
            ->has('limits', 1)
            ->where('limits.0.category_id', $category->id)
            ->where('limits.0.amount', 1200)
            ->where('limits.0.spent', 1500)
            ->where('limits.0.exceeded', true)
        );

    $this->actingAs($user)
        ->from(route('limits.index'))
        ->delete(route('limits.destroy', $limit))
        ->assertRedirect(route('limits.index'));

    expect(CategoryLimit::query()->find($limit->id))->toBeNull();
});

it('hides another workspace limit on index and write', function () {
    $a = User::factory()->create();
    $b = User::factory()->create();
    (new CreateWorkspace)->execute($a, 'A', 'RUB');
    $wsB = (new CreateWorkspace)->execute($b, 'B', 'RUB');
    $categoryB = $wsB->categories()->where('kind', 'expense')->first();
    $limitB = (new UpsertCategoryLimit)->execute($wsB, $categoryB, 1000);

    $this->withoutVite()
        ->actingAs($a)
        ->get(route('limits.index'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('limits/Index')
            ->has('limits', 0)
        );

    $this->actingAs($a)
        ->patch(route('limits.update', $limitB), [
            'amount' => '20.00',
        ])
        ->assertForbidden();
});
