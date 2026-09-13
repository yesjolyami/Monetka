<?php

use App\Actions\Accounts\ArchiveAccount;
use App\Actions\Accounts\CreateAccount;
use App\Actions\Transactions\RecordExpense;
use App\Actions\Transactions\RecordIncome;
use App\Actions\Workspaces\CreateWorkspace;
use App\Models\User;
use App\Support\AccountBalance;
use Illuminate\Validation\ValidationException;
use Inertia\Testing\AssertableInertia;

it('records income and expense against the same account', function () {
    $user = User::factory()->create();
    $workspace = (new CreateWorkspace)->execute($user, 'Семья', 'RUB');
    $account = (new CreateAccount)->execute($workspace, $user, [
        'name' => 'Карта', 'type' => 'checking', 'bank_id' => null, 'user_id' => null, 'opening_balance' => 0,
    ]);
    $incomeCat = $workspace->categories()->where('kind', 'income')->first();
    $expenseCat = $workspace->categories()->where('kind', 'expense')->first();

    (new RecordIncome)->execute($workspace, $user, [
        'account_id' => $account->id,
        'category_id' => $incomeCat->id,
        'amount' => 10000,
        'occurred_on' => '2026-09-01',
        'description' => 'Зарплата',
    ]);
    (new RecordExpense)->execute($workspace, $user, [
        'account_id' => $account->id,
        'category_id' => $expenseCat->id,
        'amount' => 2500,
        'occurred_on' => '2026-09-02',
        'description' => 'Продукты',
    ]);

    expect(AccountBalance::for($account->fresh()))->toBe(7500);
});

it('rejects an expense on another workspace account', function () {
    $a = User::factory()->create();
    $b = User::factory()->create();
    $wsA = (new CreateWorkspace)->execute($a, 'A', 'RUB');
    $wsB = (new CreateWorkspace)->execute($b, 'B', 'RUB');
    $accountB = (new CreateAccount)->execute($wsB, $b, [
        'name' => 'Чужая', 'type' => 'checking', 'bank_id' => null, 'user_id' => null, 'opening_balance' => 0,
    ]);
    $cat = $wsA->categories()->where('kind', 'expense')->first();

    (new RecordExpense)->execute($wsA, $a, [
        'account_id' => $accountB->id,
        'category_id' => $cat->id,
        'amount' => 100,
        'occurred_on' => '2026-09-01',
        'description' => 'x',
    ]);
})->throws(ValidationException::class);

it('rejects an expense on another workspace category', function () {
    $a = User::factory()->create();
    $b = User::factory()->create();
    $wsA = (new CreateWorkspace)->execute($a, 'A', 'RUB');
    $wsB = (new CreateWorkspace)->execute($b, 'B', 'RUB');
    $accountA = (new CreateAccount)->execute($wsA, $a, [
        'name' => 'Своя', 'type' => 'checking', 'bank_id' => null, 'user_id' => null, 'opening_balance' => 0,
    ]);
    $catB = $wsB->categories()->where('kind', 'expense')->first();

    (new RecordExpense)->execute($wsA, $a, [
        'account_id' => $accountA->id,
        'category_id' => $catB->id,
        'amount' => 100,
        'occurred_on' => '2026-09-01',
        'description' => 'x',
    ]);
})->throws(ValidationException::class);

it('rejects an expense on an archived account', function () {
    $user = User::factory()->create();
    $workspace = (new CreateWorkspace)->execute($user, 'Семья', 'RUB');
    $account = (new CreateAccount)->execute($workspace, $user, [
        'name' => 'Карта', 'type' => 'checking', 'bank_id' => null, 'user_id' => null, 'opening_balance' => 0,
    ]);
    (new ArchiveAccount)->execute($account);
    $cat = $workspace->categories()->where('kind', 'expense')->first();

    (new RecordExpense)->execute($workspace, $user, [
        'account_id' => $account->id,
        'category_id' => $cat->id,
        'amount' => 100,
        'occurred_on' => '2026-09-01',
        'description' => 'x',
    ]);
})->throws(ValidationException::class);

it('rejects an expense with an income category', function () {
    $user = User::factory()->create();
    $workspace = (new CreateWorkspace)->execute($user, 'Семья', 'RUB');
    $account = (new CreateAccount)->execute($workspace, $user, [
        'name' => 'Карта', 'type' => 'checking', 'bank_id' => null, 'user_id' => null, 'opening_balance' => 0,
    ]);
    $incomeCat = $workspace->categories()->where('kind', 'income')->first();

    (new RecordExpense)->execute($workspace, $user, [
        'account_id' => $account->id,
        'category_id' => $incomeCat->id,
        'amount' => 100,
        'occurred_on' => '2026-09-01',
        'description' => 'x',
    ]);
})->throws(ValidationException::class);

it('stores an expense via http and lists it on the index', function () {
    $user = User::factory()->create();
    $workspace = (new CreateWorkspace)->execute($user, 'Семья', 'RUB');
    $account = (new CreateAccount)->execute($workspace, $user, [
        'name' => 'Карта',
        'type' => 'checking',
        'bank_id' => null,
        'user_id' => null,
        'opening_balance' => 0,
    ]);
    $expenseCat = $workspace->categories()->where('kind', 'expense')->first();
    $occurredOn = now()->toDateString();

    $this->actingAs($user)
        ->from(route('transactions.index'))
        ->post(route('transactions.expense'), [
            'account_id' => $account->id,
            'category_id' => $expenseCat->id,
            'amount' => '25.00',
            'occurred_on' => $occurredOn,
            'description' => 'Продукты',
        ])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    $this->withoutVite()
        ->get(route('transactions.index'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('transactions/Index')
            ->has('transactions', 1)
            ->where('transactions.0.description', 'Продукты')
            ->where('transactions.0.amount', 2500)
            ->where('transactions.0.type', 'expense')
            ->where('transactions.0.account.name', 'Карта')
            ->where('transactions.0.category.name', $expenseCat->name)
        );
});
