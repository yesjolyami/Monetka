<?php

use App\Actions\Accounts\ArchiveAccount;
use App\Actions\Accounts\CreateAccount;
use App\Actions\Accounts\SetAccountBalance;
use App\Actions\Transactions\DeleteTransaction;
use App\Actions\Transactions\RecordExpense;
use App\Actions\Transactions\TransferBetweenAccounts;
use App\Actions\Transactions\UpdateTransaction;
use App\Actions\Workspaces\CreateWorkspace;
use App\Models\User;
use App\Support\AccountBalance;
use Illuminate\Validation\ValidationException;
use Inertia\Testing\AssertableInertia;

it('creates a signed adjustment so the ledger matches the target', function () {
    $user = User::factory()->create();
    $ws = (new CreateWorkspace)->execute($user, 'Семья', 'RUB');
    $account = (new CreateAccount)->execute($ws, $user, ['name' => 'A', 'type' => 'checking', 'bank_id' => null, 'user_id' => null, 'opening_balance' => 0]);
    $cat = $ws->categories()->where('kind', 'expense')->first();
    (new RecordExpense)->execute($ws, $user, [
        'account_id' => $account->id, 'category_id' => $cat->id, 'amount' => 300, 'occurred_on' => '2026-09-01', 'description' => 'x',
    ]);

    (new SetAccountBalance)->execute($account, $user, 1000, '2026-09-09');

    expect(AccountBalance::for($account->fresh()))->toBe(1000);
});

it('inserts nothing when the target already matches the ledger', function () {
    $user = User::factory()->create();
    $ws = (new CreateWorkspace)->execute($user, 'Семья', 'RUB');
    $account = (new CreateAccount)->execute($ws, $user, ['name' => 'A', 'type' => 'checking', 'bank_id' => null, 'user_id' => null, 'opening_balance' => 0]);

    $result = (new SetAccountBalance)->execute($account, $user, 0, '2026-09-09');

    expect($result)->toBeNull()
        ->and($account->transactions()->count())->toBe(0);
});

it('rejects setting the balance of an archived account', function () {
    $user = User::factory()->create();
    $ws = (new CreateWorkspace)->execute($user, 'Семья', 'RUB');
    $account = (new CreateAccount)->execute($ws, $user, ['name' => 'A', 'type' => 'checking', 'bank_id' => null, 'user_id' => null, 'opening_balance' => 0]);
    (new ArchiveAccount)->execute($account);

    (new SetAccountBalance)->execute($account, $user, 1000, '2026-09-09');
})->throws(ValidationException::class);

it('sets the balance via http and lists the adjustment on the show page', function () {
    $user = User::factory()->create();
    $ws = (new CreateWorkspace)->execute($user, 'Семья', 'RUB');
    $account = (new CreateAccount)->execute($ws, $user, ['name' => 'A', 'type' => 'checking', 'bank_id' => null, 'user_id' => null, 'opening_balance' => 0]);
    $cat = $ws->categories()->where('kind', 'expense')->first();
    (new RecordExpense)->execute($ws, $user, [
        'account_id' => $account->id, 'category_id' => $cat->id, 'amount' => 300, 'occurred_on' => '2026-09-01', 'description' => 'x',
    ]);

    $this->actingAs($user)
        ->from(route('accounts.show', $account))
        ->post(route('accounts.balance', $account), [
            'amount' => '10.00',
            'occurred_on' => '2026-09-09',
        ])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    expect(AccountBalance::for($account->fresh()))->toBe(1000);

    $this->withoutVite()
        ->get(route('accounts.show', ['account' => $account, 'month' => '2026-09']))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('accounts/Show')
            ->where('account.id', $account->id)
            ->where('account.name', 'A')
            ->where('account.balance', 1000)
            ->where('filters.month', '2026-09')
            ->has('transactions', 2)
            ->where('transactions.0.type', 'adjustment')
            ->where('transactions.0.amount', 1300)
            ->where('transactions.0.effect', 1300)
        );
});

it('lists transfers where the account is the counterparty and filters by month', function () {
    $user = User::factory()->create();
    $ws = (new CreateWorkspace)->execute($user, 'Семья', 'RUB');
    $from = (new CreateAccount)->execute($ws, $user, ['name' => 'A', 'type' => 'checking', 'bank_id' => null, 'user_id' => null, 'opening_balance' => 0]);
    $to = (new CreateAccount)->execute($ws, $user, ['name' => 'B', 'type' => 'savings', 'bank_id' => null, 'user_id' => null, 'opening_balance' => 0]);
    $cat = $ws->categories()->where('kind', 'expense')->first();

    (new RecordExpense)->execute($ws, $user, [
        'account_id' => $to->id, 'category_id' => $cat->id, 'amount' => 50, 'occurred_on' => '2026-08-15', 'description' => 'old',
    ]);
    (new TransferBetweenAccounts)->execute($ws, $user, [
        'account_id' => $from->id,
        'counterparty_account_id' => $to->id,
        'amount' => 400,
        'occurred_on' => '2026-09-05',
        'description' => 'перевод',
    ]);

    $this->withoutVite()
        ->actingAs($user)
        ->get(route('accounts.show', ['account' => $to, 'month' => '2026-09']))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('accounts/Show')
            ->has('transactions', 1)
            ->where('transactions.0.type', 'transfer')
            ->where('transactions.0.amount', 400)
            ->where('transactions.0.effect', 400)
            ->where('transactions.0.counterparty_account.name', 'B')
        );
});

it('hides another workspace account on show and balance', function () {
    $a = User::factory()->create();
    $b = User::factory()->create();
    (new CreateWorkspace)->execute($a, 'A', 'RUB');
    $wsB = (new CreateWorkspace)->execute($b, 'B', 'RUB');
    $accountB = (new CreateAccount)->execute($wsB, $b, ['name' => 'Чужая', 'type' => 'checking', 'bank_id' => null, 'user_id' => null, 'opening_balance' => 0]);

    $this->actingAs($a)
        ->get(route('accounts.show', $accountB))
        ->assertForbidden();

    $this->actingAs($a)
        ->post(route('accounts.balance', $accountB), [
            'amount' => '10.00',
            'occurred_on' => '2026-09-09',
        ])
        ->assertForbidden();
});

it('rejects updating or deleting an adjustment', function () {
    $user = User::factory()->create();
    $ws = (new CreateWorkspace)->execute($user, 'Семья', 'RUB');
    $account = (new CreateAccount)->execute($ws, $user, [
        'name' => 'A',
        'type' => 'checking',
        'bank_id' => null,
        'user_id' => null,
        'opening_balance' => 5000,
    ]);
    $adjustment = $account->transactions()->where('type', 'adjustment')->firstOrFail();

    expect(fn () => (new UpdateTransaction)->execute($adjustment, $user, [
        'account_id' => $account->id,
        'amount' => 1000,
        'occurred_on' => '2026-09-09',
        'description' => 'нет',
    ]))->toThrow(ValidationException::class);

    expect(fn () => (new DeleteTransaction)->execute($adjustment))
        ->toThrow(ValidationException::class);

    $this->actingAs($user)
        ->from(route('transactions.index'))
        ->patch(route('transactions.update', $adjustment), [
            'account_id' => $account->id,
            'amount' => '10.00',
            'occurred_on' => '2026-09-09',
            'description' => 'нет',
        ])
        ->assertRedirect()
        ->assertSessionHasErrors('transaction');

    $this->actingAs($user)
        ->from(route('transactions.index'))
        ->delete(route('transactions.destroy', $adjustment))
        ->assertRedirect()
        ->assertSessionHasErrors('transaction');

    expect($adjustment->fresh())->not->toBeNull()
        ->and(AccountBalance::for($account->fresh()))->toBe(5000);
});
