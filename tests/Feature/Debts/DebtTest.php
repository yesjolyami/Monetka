<?php

use App\Actions\Accounts\ArchiveAccount;
use App\Actions\Accounts\CreateAccount;
use App\Actions\Debts\CreateDebt;
use App\Actions\Debts\DeleteDebt;
use App\Actions\Debts\RepayDebt;
use App\Actions\Debts\UpdateDebt;
use App\Actions\Workspaces\CreateWorkspace;
use App\Enums\DebtDirection;
use App\Models\Debt;
use App\Models\User;
use App\Support\AccountBalance;
use Illuminate\Validation\ValidationException;
use Inertia\Testing\AssertableInertia;

it('reduces remainder without touching accounts when no account given', function () {
    $user = User::factory()->create();
    $ws = (new CreateWorkspace)->execute($user, 'Семья', 'RUB');
    $debt = (new CreateDebt)->execute($ws, [
        'direction' => DebtDirection::TheyOwe,
        'counterparty_name' => 'Вася',
        'original_amount' => 3000,
        'notes' => null,
    ]);
    (new RepayDebt)->execute($debt, $user, 1000, null, '2026-09-09', 'часть');
    expect($debt->fresh()->remainder())->toBe(2000);
});

it('credits the account when they repay you', function () {
    $user = User::factory()->create();
    $ws = (new CreateWorkspace)->execute($user, 'Семья', 'RUB');
    $account = (new CreateAccount)->execute($ws, $user, ['name' => 'A', 'type' => 'checking', 'bank_id' => null, 'user_id' => null, 'opening_balance' => 0]);
    $debt = (new CreateDebt)->execute($ws, [
        'direction' => DebtDirection::TheyOwe,
        'counterparty_name' => 'Вася',
        'original_amount' => 3000,
        'notes' => null,
    ]);
    (new RepayDebt)->execute($debt, $user, 3000, $account, '2026-09-09', 'вернул');
    expect(AccountBalance::for($account->fresh()))->toBe(3000)
        ->and($debt->fresh()->remainder())->toBe(0);
});

it('rejects a repayment larger than the remainder', function () {
    $user = User::factory()->create();
    $ws = (new CreateWorkspace)->execute($user, 'Семья', 'RUB');
    $debt = (new CreateDebt)->execute($ws, [
        'direction' => DebtDirection::IOwe,
        'counterparty_name' => 'Банк',
        'original_amount' => 500,
        'notes' => null,
    ]);
    (new RepayDebt)->execute($debt, $user, 600, null, '2026-09-09', '');
})->throws(ValidationException::class);

it('debits the account when you repay them', function () {
    $user = User::factory()->create();
    $ws = (new CreateWorkspace)->execute($user, 'Семья', 'RUB');
    $account = (new CreateAccount)->execute($ws, $user, ['name' => 'A', 'type' => 'checking', 'bank_id' => null, 'user_id' => null, 'opening_balance' => 5000]);
    $debt = (new CreateDebt)->execute($ws, [
        'direction' => DebtDirection::IOwe,
        'counterparty_name' => 'Банк',
        'original_amount' => 3000,
        'notes' => null,
    ]);
    (new RepayDebt)->execute($debt, $user, 1000, $account, '2026-09-09', 'часть');
    expect(AccountBalance::for($account->fresh()))->toBe(4000)
        ->and($debt->fresh()->remainder())->toBe(2000);
});

it('refuses a repayment on an archived account', function () {
    $user = User::factory()->create();
    $ws = (new CreateWorkspace)->execute($user, 'Семья', 'RUB');
    $account = (new CreateAccount)->execute($ws, $user, ['name' => 'A', 'type' => 'checking', 'bank_id' => null, 'user_id' => null, 'opening_balance' => 0]);
    (new ArchiveAccount)->execute($account);
    $debt = (new CreateDebt)->execute($ws, [
        'direction' => DebtDirection::TheyOwe,
        'counterparty_name' => 'Вася',
        'original_amount' => 3000,
        'notes' => null,
    ]);

    (new RepayDebt)->execute($debt, $user, 1000, $account, '2026-09-09', 'часть');
})->throws(ValidationException::class);

it('refuses a repayment from another workspace account', function () {
    $a = User::factory()->create();
    $b = User::factory()->create();
    $wsA = (new CreateWorkspace)->execute($a, 'A', 'RUB');
    $wsB = (new CreateWorkspace)->execute($b, 'B', 'RUB');
    $accountB = (new CreateAccount)->execute($wsB, $b, ['name' => 'Чужая', 'type' => 'checking', 'bank_id' => null, 'user_id' => null, 'opening_balance' => 0]);
    $debt = (new CreateDebt)->execute($wsA, [
        'direction' => DebtDirection::TheyOwe,
        'counterparty_name' => 'Вася',
        'original_amount' => 3000,
        'notes' => null,
    ]);

    (new RepayDebt)->execute($debt, $a, 1000, $accountB, '2026-09-09', 'часть');
})->throws(ValidationException::class);

it('refuses to delete a debt that has repayments', function () {
    $user = User::factory()->create();
    $ws = (new CreateWorkspace)->execute($user, 'Семья', 'RUB');
    $debt = (new CreateDebt)->execute($ws, [
        'direction' => DebtDirection::TheyOwe,
        'counterparty_name' => 'Вася',
        'original_amount' => 3000,
        'notes' => null,
    ]);
    (new RepayDebt)->execute($debt, $user, 1000, null, '2026-09-09', 'часть');

    (new DeleteDebt)->execute($debt);
})->throws(ValidationException::class);

it('updates a debt and deletes one without repayments', function () {
    $user = User::factory()->create();
    $ws = (new CreateWorkspace)->execute($user, 'Семья', 'RUB');
    $debt = (new CreateDebt)->execute($ws, [
        'direction' => DebtDirection::TheyOwe,
        'counterparty_name' => 'Вася',
        'original_amount' => 3000,
        'notes' => 'За обед',
    ]);

    (new UpdateDebt)->execute($debt, [
        'direction' => DebtDirection::IOwe,
        'counterparty_name' => 'Банк',
        'original_amount' => 5000,
        'notes' => null,
    ]);

    $debt->refresh();

    expect($debt->direction)->toBe(DebtDirection::IOwe)
        ->and($debt->counterparty_name)->toBe('Банк')
        ->and($debt->original_amount)->toBe(5000)
        ->and($debt->notes)->toBeNull();

    (new DeleteDebt)->execute($debt);

    expect(Debt::query()->find($debt->id))->toBeNull();
});

it('creates a debt via http and lists remainder', function () {
    $user = User::factory()->create();
    $ws = (new CreateWorkspace)->execute($user, 'Семья', 'RUB');
    $account = (new CreateAccount)->execute($ws, $user, [
        'name' => 'A',
        'type' => 'checking',
        'bank_id' => null,
        'user_id' => null,
        'opening_balance' => 0,
    ]);

    $this->actingAs($user)
        ->from(route('debts.index'))
        ->post(route('debts.store'), [
            'direction' => 'they_owe',
            'counterparty_name' => 'Вася',
            'original_amount' => '30.00',
            'notes' => 'За обед',
        ])
        ->assertRedirect(route('debts.index'));

    $debt = $ws->debts()->where('counterparty_name', 'Вася')->firstOrFail();

    expect($debt->original_amount)->toBe(3000);

    $this->actingAs($user)
        ->from(route('debts.index'))
        ->post(route('debts.repay', $debt), [
            'account_id' => $account->id,
            'amount' => '10.00',
            'occurred_on' => '2026-09-09',
            'description' => 'часть',
        ])
        ->assertRedirect(route('debts.index'))
        ->assertSessionHasNoErrors();

    expect($debt->fresh()->remainder())->toBe(2000)
        ->and(AccountBalance::for($account->fresh()))->toBe(1000);

    $this->withoutVite()
        ->get(route('debts.index'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('debts/Index')
            ->has('debts', 1)
            ->where('debts.0.counterparty_name', 'Вася')
            ->where('debts.0.direction', 'they_owe')
            ->where('debts.0.original_amount', 3000)
            ->where('debts.0.remainder', 2000)
            ->where('debts.0.notes', 'За обед')
        );
});

it('hides another workspace debt on index and write', function () {
    $a = User::factory()->create();
    $b = User::factory()->create();
    (new CreateWorkspace)->execute($a, 'A', 'RUB');
    $wsB = (new CreateWorkspace)->execute($b, 'B', 'RUB');
    $accountB = (new CreateAccount)->execute($wsB, $b, ['name' => 'Чужая', 'type' => 'checking', 'bank_id' => null, 'user_id' => null, 'opening_balance' => 0]);
    $debtB = (new CreateDebt)->execute($wsB, [
        'direction' => DebtDirection::TheyOwe,
        'counterparty_name' => 'Чужой',
        'original_amount' => 1000,
        'notes' => null,
    ]);

    $this->withoutVite()
        ->actingAs($a)
        ->get(route('debts.index'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('debts/Index')
            ->has('debts', 0)
        );

    $this->actingAs($a)
        ->post(route('debts.repay', $debtB), [
            'account_id' => $accountB->id,
            'amount' => '10.00',
            'occurred_on' => '2026-09-09',
        ])
        ->assertForbidden();
});
