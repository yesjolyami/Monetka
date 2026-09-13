<?php

use App\Actions\Accounts\ArchiveAccount;
use App\Actions\Accounts\CreateAccount;
use App\Actions\Goals\ContributeToGoal;
use App\Actions\Goals\CreateGoal;
use App\Actions\Goals\DeleteGoal;
use App\Actions\Goals\UpdateGoal;
use App\Actions\Goals\WithdrawFromGoal;
use App\Actions\Workspaces\CreateWorkspace;
use App\Models\Goal;
use App\Models\User;
use App\Support\AccountBalance;
use Illuminate\Validation\ValidationException;
use Inertia\Testing\AssertableInertia;

it('moves money from an account onto a goal and back', function () {
    $user = User::factory()->create();
    $ws = (new CreateWorkspace)->execute($user, 'Семья', 'RUB');
    $account = (new CreateAccount)->execute($ws, $user, ['name' => 'A', 'type' => 'checking', 'bank_id' => null, 'user_id' => null, 'opening_balance' => 8000]);
    $goal = (new CreateGoal)->execute($ws, ['name' => 'Отпуск', 'target_amount' => 50000, 'target_date' => '2026-12-01', 'notes' => null]);

    (new ContributeToGoal)->execute($goal, $user, $account, 3000, '2026-09-09');
    expect($goal->fresh()->progress())->toBe(3000)
        ->and(AccountBalance::for($account->fresh()))->toBe(5000);

    (new WithdrawFromGoal)->execute($goal, $user, $account, 1000, '2026-09-10');
    expect($goal->fresh()->progress())->toBe(2000)
        ->and(AccountBalance::for($account->fresh()))->toBe(6000);
});

it('refuses a withdrawal larger than current progress', function () {
    $user = User::factory()->create();
    $ws = (new CreateWorkspace)->execute($user, 'Семья', 'RUB');
    $account = (new CreateAccount)->execute($ws, $user, ['name' => 'A', 'type' => 'checking', 'bank_id' => null, 'user_id' => null, 'opening_balance' => 8000]);
    $goal = (new CreateGoal)->execute($ws, ['name' => 'Отпуск', 'target_amount' => 50000, 'target_date' => null, 'notes' => null]);

    (new ContributeToGoal)->execute($goal, $user, $account, 1000, '2026-09-09');
    (new WithdrawFromGoal)->execute($goal, $user, $account, 1001, '2026-09-10');
})->throws(ValidationException::class);

it('refuses a contribution on an archived account', function () {
    $user = User::factory()->create();
    $ws = (new CreateWorkspace)->execute($user, 'Семья', 'RUB');
    $account = (new CreateAccount)->execute($ws, $user, ['name' => 'A', 'type' => 'checking', 'bank_id' => null, 'user_id' => null, 'opening_balance' => 8000]);
    (new ArchiveAccount)->execute($account);
    $goal = (new CreateGoal)->execute($ws, ['name' => 'Отпуск', 'target_amount' => 50000, 'target_date' => null, 'notes' => null]);

    (new ContributeToGoal)->execute($goal, $user, $account, 1000, '2026-09-09');
})->throws(ValidationException::class);

it('refuses a contribution from another workspace account', function () {
    $a = User::factory()->create();
    $b = User::factory()->create();
    $wsA = (new CreateWorkspace)->execute($a, 'A', 'RUB');
    $wsB = (new CreateWorkspace)->execute($b, 'B', 'RUB');
    $accountB = (new CreateAccount)->execute($wsB, $b, ['name' => 'Чужая', 'type' => 'checking', 'bank_id' => null, 'user_id' => null, 'opening_balance' => 8000]);
    $goal = (new CreateGoal)->execute($wsA, ['name' => 'Отпуск', 'target_amount' => 50000, 'target_date' => null, 'notes' => null]);

    (new ContributeToGoal)->execute($goal, $a, $accountB, 1000, '2026-09-09');
})->throws(ValidationException::class);

it('refuses to delete a goal that has transactions', function () {
    $user = User::factory()->create();
    $ws = (new CreateWorkspace)->execute($user, 'Семья', 'RUB');
    $account = (new CreateAccount)->execute($ws, $user, ['name' => 'A', 'type' => 'checking', 'bank_id' => null, 'user_id' => null, 'opening_balance' => 8000]);
    $goal = (new CreateGoal)->execute($ws, ['name' => 'Отпуск', 'target_amount' => 50000, 'target_date' => null, 'notes' => null]);
    (new ContributeToGoal)->execute($goal, $user, $account, 1000, '2026-09-09');

    (new DeleteGoal)->execute($goal);
})->throws(ValidationException::class);

it('updates a goal and deletes one without transactions', function () {
    $user = User::factory()->create();
    $ws = (new CreateWorkspace)->execute($user, 'Семья', 'RUB');
    $goal = (new CreateGoal)->execute($ws, ['name' => 'Отпуск', 'target_amount' => 50000, 'target_date' => '2026-12-01', 'notes' => 'Море']);

    (new UpdateGoal)->execute($goal, [
        'name' => 'Ремонт',
        'target_amount' => 80000,
        'target_date' => null,
        'notes' => null,
    ]);

    $goal->refresh();

    expect($goal->name)->toBe('Ремонт')
        ->and($goal->target_amount)->toBe(80000)
        ->and($goal->target_date)->toBeNull()
        ->and($goal->notes)->toBeNull();

    (new DeleteGoal)->execute($goal);

    expect(Goal::query()->find($goal->id))->toBeNull();
});

it('creates a goal via http and lists progress', function () {
    $user = User::factory()->create();
    $ws = (new CreateWorkspace)->execute($user, 'Семья', 'RUB');
    $account = (new CreateAccount)->execute($ws, $user, [
        'name' => 'A',
        'type' => 'checking',
        'bank_id' => null,
        'user_id' => null,
        'opening_balance' => 8000,
    ]);

    $this->actingAs($user)
        ->from(route('goals.index'))
        ->post(route('goals.store'), [
            'name' => 'Отпуск',
            'target_amount' => '500.00',
            'target_date' => '2026-12-01',
            'notes' => 'Море',
        ])
        ->assertRedirect(route('goals.index'));

    $goal = $ws->goals()->where('name', 'Отпуск')->firstOrFail();

    expect($goal->target_amount)->toBe(50000);

    $this->actingAs($user)
        ->from(route('goals.index'))
        ->post(route('goals.contribute', $goal), [
            'account_id' => $account->id,
            'amount' => '30.00',
            'occurred_on' => '2026-09-09',
        ])
        ->assertRedirect(route('goals.index'))
        ->assertSessionHasNoErrors();

    expect($goal->fresh()->progress())->toBe(3000)
        ->and(AccountBalance::for($account->fresh()))->toBe(5000);

    $this->withoutVite()
        ->get(route('goals.index'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('goals/Index')
            ->has('goals', 1)
            ->where('goals.0.name', 'Отпуск')
            ->where('goals.0.target_amount', 50000)
            ->where('goals.0.progress', 3000)
            ->where('goals.0.target_date', '2026-12-01')
            ->where('goals.0.notes', 'Море')
        );
});

it('hides another workspace goal on index and write', function () {
    $a = User::factory()->create();
    $b = User::factory()->create();
    (new CreateWorkspace)->execute($a, 'A', 'RUB');
    $wsB = (new CreateWorkspace)->execute($b, 'B', 'RUB');
    $accountB = (new CreateAccount)->execute($wsB, $b, ['name' => 'Чужая', 'type' => 'checking', 'bank_id' => null, 'user_id' => null, 'opening_balance' => 8000]);
    $goalB = (new CreateGoal)->execute($wsB, ['name' => 'Чужая цель', 'target_amount' => 1000, 'target_date' => null, 'notes' => null]);

    $this->withoutVite()
        ->actingAs($a)
        ->get(route('goals.index'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('goals/Index')
            ->has('goals', 0)
        );

    $this->actingAs($a)
        ->post(route('goals.contribute', $goalB), [
            'account_id' => $accountB->id,
            'amount' => '10.00',
            'occurred_on' => '2026-09-09',
        ])
        ->assertForbidden();
});
