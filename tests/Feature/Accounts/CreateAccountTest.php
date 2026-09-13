<?php

use App\Actions\Accounts\ArchiveAccount;
use App\Actions\Accounts\CreateAccount;
use App\Actions\Accounts\DeleteAccount;
use App\Actions\Banks\CreateBank;
use App\Actions\Workspaces\CreateWorkspace;
use App\Models\Account;
use App\Models\User;
use App\Support\AccountBalance;
use Illuminate\Validation\ValidationException;
use Inertia\Testing\AssertableInertia;

it('writes an opening adjustment when starting balance is not zero', function () {
    $user = User::factory()->create();
    $workspace = (new CreateWorkspace)->execute($user, 'Семья', 'RUB');

    $account = (new CreateAccount)->execute($workspace, $user, [
        'name' => 'Карта',
        'type' => 'checking',
        'bank_id' => null,
        'user_id' => null,
        'opening_balance' => 5000,
    ]);

    expect(AccountBalance::for($account))->toBe(5000)
        ->and($account->transactions()->where('type', 'adjustment')->value('amount'))->toBe(5000);
});

it('stores an account via http and converts the opening balance', function () {
    $user = User::factory()->create();
    (new CreateWorkspace)->execute($user, 'Семья', 'RUB');

    $this->actingAs($user)
        ->post(route('accounts.store'), [
            'name' => 'Карта',
            'type' => 'checking',
            'opening_balance' => '50.00',
        ])
        ->assertRedirect(route('accounts.index'));

    $account = Account::query()->first();

    expect(AccountBalance::for($account))->toBe(5000);
});

it('refuses to delete an account that has transactions', function () {
    $user = User::factory()->create();
    $workspace = (new CreateWorkspace)->execute($user, 'Семья', 'RUB');
    $account = (new CreateAccount)->execute($workspace, $user, [
        'name' => 'Карта',
        'type' => 'checking',
        'bank_id' => null,
        'user_id' => null,
        'opening_balance' => 5000,
    ]);

    expect(fn () => (new DeleteAccount)->execute($account))
        ->toThrow(ValidationException::class);
});

it('archives an account and forbids new activity', function () {
    $user = User::factory()->create();
    $workspace = (new CreateWorkspace)->execute($user, 'Семья', 'RUB');
    $account = (new CreateAccount)->execute($workspace, $user, [
        'name' => 'Карта',
        'type' => 'checking',
        'bank_id' => null,
        'user_id' => null,
        'opening_balance' => 0,
    ]);

    (new ArchiveAccount)->execute($account);

    expect($account->fresh()->archived_at)->not->toBeNull()
        ->and(fn () => $account->fresh()->assertActive())
        ->toThrow(ValidationException::class);
});

it('groups accounts by bank and skips archived by default', function () {
    $user = User::factory()->create();
    $workspace = (new CreateWorkspace)->execute($user, 'Семья', 'RUB');
    $bank = (new CreateBank)->execute($workspace, ['name' => 'Тинькофф']);

    (new CreateAccount)->execute($workspace, $user, [
        'name' => 'Карта',
        'type' => 'checking',
        'bank_id' => $bank->id,
        'user_id' => null,
        'opening_balance' => 1000,
    ]);
    $cash = (new CreateAccount)->execute($workspace, $user, [
        'name' => 'Наличные',
        'type' => 'checking',
        'bank_id' => null,
        'user_id' => $user->id,
        'opening_balance' => 200,
    ]);
    $archived = (new CreateAccount)->execute($workspace, $user, [
        'name' => 'Старый',
        'type' => 'savings',
        'bank_id' => $bank->id,
        'user_id' => null,
        'opening_balance' => 0,
    ]);
    (new ArchiveAccount)->execute($archived);

    $this->withoutVite()
        ->actingAs($user)
        ->get(route('accounts.index'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('accounts/Index')
            ->has('groups', 2)
            ->where('groups.0.name', 'Тинькофф')
            ->where('groups.0.total', 1000)
            ->where('groups.0.shared.0.name', 'Карта')
            ->has('groups.0.shared', 1)
            ->where('groups.1.name', 'Без банка')
            ->where('groups.1.total', 200)
            ->where('groups.1.personal.0.id', $cash->id)
        );
});
