<?php

use App\Actions\Accounts\ArchiveAccount;
use App\Actions\Accounts\CreateAccount;
use App\Actions\Transactions\DeleteTransaction;
use App\Actions\Transactions\RecordExpense;
use App\Actions\Transactions\TransferBetweenAccounts;
use App\Actions\Transactions\UpdateTransaction;
use App\Actions\Workspaces\CreateWorkspace;
use App\Enums\TransactionType;
use App\Models\User;
use App\Support\AccountBalance;
use Illuminate\Validation\ValidationException;
use Inertia\Testing\AssertableInertia;

it('moves money between accounts and restores after delete', function () {
    $user = User::factory()->create();
    $ws = (new CreateWorkspace)->execute($user, 'Семья', 'RUB');
    $from = (new CreateAccount)->execute($ws, $user, ['name' => 'A', 'type' => 'checking', 'bank_id' => null, 'user_id' => null, 'opening_balance' => 5000]);
    $to = (new CreateAccount)->execute($ws, $user, ['name' => 'B', 'type' => 'savings', 'bank_id' => null, 'user_id' => null, 'opening_balance' => 0]);

    $tx = (new TransferBetweenAccounts)->execute($ws, $user, [
        'account_id' => $from->id,
        'counterparty_account_id' => $to->id,
        'amount' => 1500,
        'occurred_on' => '2026-09-03',
        'description' => 'На копилку',
    ]);

    expect(AccountBalance::for($from->fresh()))->toBe(3500)
        ->and(AccountBalance::for($to->fresh()))->toBe(1500);

    (new DeleteTransaction)->execute($tx);

    expect(AccountBalance::for($from->fresh()))->toBe(5000)
        ->and(AccountBalance::for($to->fresh()))->toBe(0);
});

it('rejects transfer to the same account', function () {
    $user = User::factory()->create();
    $ws = (new CreateWorkspace)->execute($user, 'Семья', 'RUB');
    $account = (new CreateAccount)->execute($ws, $user, ['name' => 'A', 'type' => 'checking', 'bank_id' => null, 'user_id' => null, 'opening_balance' => 0]);

    (new TransferBetweenAccounts)->execute($ws, $user, [
        'account_id' => $account->id,
        'counterparty_account_id' => $account->id,
        'amount' => 100,
        'occurred_on' => '2026-09-03',
        'description' => '',
    ]);
})->throws(ValidationException::class);

it('rejects a same-account transfer via http', function () {
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
        ->postJson(route('transactions.transfer'), [
            'account_id' => $account->id,
            'counterparty_account_id' => $account->id,
            'amount' => '1.00',
            'occurred_on' => '2026-09-03',
            'description' => '',
        ])
        ->assertUnprocessable();
});

it('forbids posting a transfer while acting on another workspace transaction', function () {
    $owner = User::factory()->create();
    $stranger = User::factory()->create();
    $wsA = (new CreateWorkspace)->execute($owner, 'A', 'RUB');
    (new CreateWorkspace)->execute($stranger, 'B', 'RUB');
    $from = (new CreateAccount)->execute($wsA, $owner, [
        'name' => 'A', 'type' => 'checking', 'bank_id' => null, 'user_id' => null, 'opening_balance' => 5000,
    ]);
    $to = (new CreateAccount)->execute($wsA, $owner, [
        'name' => 'B', 'type' => 'savings', 'bank_id' => null, 'user_id' => null, 'opening_balance' => 0,
    ]);
    $tx = (new TransferBetweenAccounts)->execute($wsA, $owner, [
        'account_id' => $from->id,
        'counterparty_account_id' => $to->id,
        'amount' => 1500,
        'occurred_on' => '2026-09-03',
        'description' => 'На копилку',
    ]);

    $this->actingAs($stranger)
        ->post(route('transactions.update', $tx), [
            '_method' => 'PATCH',
            'account_id' => $from->id,
            'counterparty_account_id' => $to->id,
            'amount' => '20.00',
            'occurred_on' => '2026-09-03',
            'description' => 'x',
        ])
        ->assertForbidden();
});

it('forbids updating a transaction from a workspace the user is not in', function () {
    $owner = User::factory()->create();
    $stranger = User::factory()->create();
    $wsA = (new CreateWorkspace)->execute($owner, 'A', 'RUB');
    (new CreateWorkspace)->execute($stranger, 'B', 'RUB');
    $from = (new CreateAccount)->execute($wsA, $owner, [
        'name' => 'A', 'type' => 'checking', 'bank_id' => null, 'user_id' => null, 'opening_balance' => 5000,
    ]);
    $to = (new CreateAccount)->execute($wsA, $owner, [
        'name' => 'B', 'type' => 'savings', 'bank_id' => null, 'user_id' => null, 'opening_balance' => 0,
    ]);
    $tx = (new TransferBetweenAccounts)->execute($wsA, $owner, [
        'account_id' => $from->id,
        'counterparty_account_id' => $to->id,
        'amount' => 1500,
        'occurred_on' => '2026-09-03',
        'description' => 'На копилку',
    ]);

    $this->actingAs($stranger)
        ->patch(route('transactions.update', $tx), [
            'account_id' => $from->id,
            'counterparty_account_id' => $to->id,
            'amount' => '20.00',
            'occurred_on' => '2026-09-03',
            'description' => 'x',
        ])
        ->assertForbidden();
});

it('forbids deleting a transaction from a workspace the user is not in', function () {
    $owner = User::factory()->create();
    $stranger = User::factory()->create();
    $wsA = (new CreateWorkspace)->execute($owner, 'A', 'RUB');
    (new CreateWorkspace)->execute($stranger, 'B', 'RUB');
    $from = (new CreateAccount)->execute($wsA, $owner, [
        'name' => 'A', 'type' => 'checking', 'bank_id' => null, 'user_id' => null, 'opening_balance' => 5000,
    ]);
    $to = (new CreateAccount)->execute($wsA, $owner, [
        'name' => 'B', 'type' => 'savings', 'bank_id' => null, 'user_id' => null, 'opening_balance' => 0,
    ]);
    $tx = (new TransferBetweenAccounts)->execute($wsA, $owner, [
        'account_id' => $from->id,
        'counterparty_account_id' => $to->id,
        'amount' => 1500,
        'occurred_on' => '2026-09-03',
        'description' => 'На копилку',
    ]);

    $this->actingAs($stranger)
        ->delete(route('transactions.destroy', $tx))
        ->assertForbidden();
});

it('rejects a new transfer on an archived account', function () {
    $user = User::factory()->create();
    $ws = (new CreateWorkspace)->execute($user, 'Семья', 'RUB');
    $from = (new CreateAccount)->execute($ws, $user, [
        'name' => 'A', 'type' => 'checking', 'bank_id' => null, 'user_id' => null, 'opening_balance' => 5000,
    ]);
    $to = (new CreateAccount)->execute($ws, $user, [
        'name' => 'B', 'type' => 'savings', 'bank_id' => null, 'user_id' => null, 'opening_balance' => 0,
    ]);
    (new ArchiveAccount)->execute($from);

    (new TransferBetweenAccounts)->execute($ws, $user, [
        'account_id' => $from->id,
        'counterparty_account_id' => $to->id,
        'amount' => 100,
        'occurred_on' => '2026-09-03',
        'description' => '',
    ]);
})->throws(ValidationException::class);

it('allows editing a transfer that uses an archived account', function () {
    $user = User::factory()->create();
    $ws = (new CreateWorkspace)->execute($user, 'Семья', 'RUB');
    $from = (new CreateAccount)->execute($ws, $user, [
        'name' => 'A', 'type' => 'checking', 'bank_id' => null, 'user_id' => null, 'opening_balance' => 5000,
    ]);
    $to = (new CreateAccount)->execute($ws, $user, [
        'name' => 'B', 'type' => 'savings', 'bank_id' => null, 'user_id' => null, 'opening_balance' => 0,
    ]);
    $tx = (new TransferBetweenAccounts)->execute($ws, $user, [
        'account_id' => $from->id,
        'counterparty_account_id' => $to->id,
        'amount' => 1500,
        'occurred_on' => '2026-09-03',
        'description' => 'На копилку',
    ]);
    (new ArchiveAccount)->execute($from);

    $updated = (new UpdateTransaction)->execute($tx, $user, [
        'account_id' => $from->id,
        'counterparty_account_id' => $to->id,
        'amount' => 2000,
        'occurred_on' => '2026-09-04',
        'description' => 'Поправка',
    ]);

    expect($updated->type)->toBe(TransactionType::Transfer)
        ->and($updated->amount)->toBe(2000)
        ->and($updated->description)->toBe('Поправка')
        ->and(AccountBalance::for($from->fresh()))->toBe(3000)
        ->and(AccountBalance::for($to->fresh()))->toBe(2000);
});

it('rejects updating a transaction onto another workspace account', function () {
    $a = User::factory()->create();
    $b = User::factory()->create();
    $wsA = (new CreateWorkspace)->execute($a, 'A', 'RUB');
    $wsB = (new CreateWorkspace)->execute($b, 'B', 'RUB');
    $accountA = (new CreateAccount)->execute($wsA, $a, [
        'name' => 'Своя', 'type' => 'checking', 'bank_id' => null, 'user_id' => null, 'opening_balance' => 0,
    ]);
    $accountB = (new CreateAccount)->execute($wsB, $b, [
        'name' => 'Чужая', 'type' => 'checking', 'bank_id' => null, 'user_id' => null, 'opening_balance' => 0,
    ]);
    $cat = $wsA->categories()->where('kind', 'expense')->first();
    $tx = (new RecordExpense)->execute($wsA, $a, [
        'account_id' => $accountA->id,
        'category_id' => $cat->id,
        'amount' => 100,
        'occurred_on' => '2026-09-01',
        'description' => 'x',
    ]);

    (new UpdateTransaction)->execute($tx, $a, [
        'account_id' => $accountB->id,
        'category_id' => $cat->id,
        'amount' => 100,
        'occurred_on' => '2026-09-01',
        'description' => 'x',
    ]);
})->throws(ValidationException::class);

it('stores a transfer via http and lists it on the index', function () {
    $user = User::factory()->create();
    $ws = (new CreateWorkspace)->execute($user, 'Семья', 'RUB');
    $from = (new CreateAccount)->execute($ws, $user, [
        'name' => 'A',
        'type' => 'checking',
        'bank_id' => null,
        'user_id' => null,
        'opening_balance' => 0,
    ]);
    $to = (new CreateAccount)->execute($ws, $user, [
        'name' => 'B',
        'type' => 'savings',
        'bank_id' => null,
        'user_id' => null,
        'opening_balance' => 0,
    ]);
    $occurredOn = now()->toDateString();

    $this->actingAs($user)
        ->from(route('transactions.index'))
        ->post(route('transactions.transfer'), [
            'account_id' => $from->id,
            'counterparty_account_id' => $to->id,
            'amount' => '15.00',
            'occurred_on' => $occurredOn,
            'description' => 'На копилку',
        ])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    $this->withoutVite()
        ->get(route('transactions.index'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('transactions/Index')
            ->has('transactions', 1)
            ->where('transactions.0.description', 'На копилку')
            ->where('transactions.0.amount', 1500)
            ->where('transactions.0.type', 'transfer')
            ->where('transactions.0.account.name', 'A')
            ->where('transactions.0.counterparty_account.name', 'B')
        );
});

it('updates and deletes a transaction via http', function () {
    $user = User::factory()->create();
    $ws = (new CreateWorkspace)->execute($user, 'Семья', 'RUB');
    $from = (new CreateAccount)->execute($ws, $user, [
        'name' => 'A', 'type' => 'checking', 'bank_id' => null, 'user_id' => null, 'opening_balance' => 5000,
    ]);
    $to = (new CreateAccount)->execute($ws, $user, [
        'name' => 'B', 'type' => 'savings', 'bank_id' => null, 'user_id' => null, 'opening_balance' => 0,
    ]);
    $tx = (new TransferBetweenAccounts)->execute($ws, $user, [
        'account_id' => $from->id,
        'counterparty_account_id' => $to->id,
        'amount' => 1500,
        'occurred_on' => now()->toDateString(),
        'description' => 'На копилку',
    ]);

    $this->actingAs($user)
        ->from(route('transactions.index'))
        ->patch(route('transactions.update', $tx), [
            'account_id' => $from->id,
            'counterparty_account_id' => $to->id,
            'amount' => '20.00',
            'occurred_on' => $tx->occurred_on->toDateString(),
            'description' => 'Поправка',
        ])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    expect($tx->fresh()->amount)->toBe(2000)
        ->and($tx->fresh()->description)->toBe('Поправка')
        ->and($tx->fresh()->type)->toBe(TransactionType::Transfer);

    $this->actingAs($user)
        ->from(route('transactions.index'))
        ->delete(route('transactions.destroy', $tx))
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    expect($tx->fresh())->toBeNull()
        ->and(AccountBalance::for($from->fresh()))->toBe(5000)
        ->and(AccountBalance::for($to->fresh()))->toBe(0);
});
