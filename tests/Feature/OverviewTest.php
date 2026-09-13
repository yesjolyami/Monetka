<?php

use App\Actions\Accounts\ArchiveAccount;
use App\Actions\Accounts\CreateAccount;
use App\Actions\Debts\CreateDebt;
use App\Actions\Goals\CreateGoal;
use App\Actions\Limits\UpsertCategoryLimit;
use App\Actions\Recurrences\CreateRecurrence;
use App\Actions\Recurrences\DeactivateRecurrence;
use App\Actions\Transactions\RecordExpense;
use App\Actions\Workspaces\CreateWorkspace;
use App\Models\User;

beforeEach(function () {
    $this->withoutVite();
});

it('shows the overview for a member', function () {
    $user = User::factory()->create();
    $ws = (new CreateWorkspace)->execute($user, 'Семья', 'RUB');
    (new CreateAccount)->execute($ws, $user, ['name' => 'A', 'type' => 'checking', 'bank_id' => null, 'user_id' => null, 'opening_balance' => 2000]);

    $this->actingAs($user)
        ->get(route('overview'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Overview')
            ->where('total', 2000));
});

it('shows a zero total when the workspace has no accounts', function () {
    $user = User::factory()->create();
    (new CreateWorkspace)->execute($user, 'Семья', 'RUB');

    $this->actingAs($user)
        ->get(route('overview'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Overview')
            ->where('total', 0)
            ->where('hasAccounts', false)
            ->has('expenseLine', now()->daysInMonth));
});

it('excludes archived accounts and lists month expenses limits goals debts and recurrences', function () {
    $user = User::factory()->create();
    $ws = (new CreateWorkspace)->execute($user, 'Семья', 'RUB');
    $open = (new CreateAccount)->execute($ws, $user, ['name' => 'A', 'type' => 'checking', 'bank_id' => null, 'user_id' => null, 'opening_balance' => 2000]);
    $archived = (new CreateAccount)->execute($ws, $user, ['name' => 'B', 'type' => 'checking', 'bank_id' => null, 'user_id' => null, 'opening_balance' => 5000]);
    (new ArchiveAccount)->execute($archived);

    $category = $ws->categories()->where('kind', 'expense')->orderBy('name')->first();
    (new RecordExpense)->execute($ws, $user, [
        'account_id' => $open->id,
        'category_id' => $category->id,
        'amount' => 300,
        'occurred_on' => now()->toDateString(),
        'description' => 'кофе',
    ]);
    (new UpsertCategoryLimit)->execute($ws, $category, 1000);
    (new CreateGoal)->execute($ws, ['name' => 'Отпуск', 'target_amount' => 50000, 'target_date' => null, 'notes' => null]);
    (new CreateDebt)->execute($ws, ['direction' => 'i_owe', 'counterparty_name' => 'Банк', 'original_amount' => 4000, 'notes' => null]);
    $active = (new CreateRecurrence)->execute($ws, [
        'type' => 'expense',
        'account_id' => $open->id,
        'counterparty_account_id' => null,
        'category_id' => $category->id,
        'amount' => 700,
        'description' => 'Подписка',
        'frequency' => 'monthly',
        'next_occurred_on' => now()->addDay()->toDateString(),
    ]);
    $inactive = (new CreateRecurrence)->execute($ws, [
        'type' => 'expense',
        'account_id' => $open->id,
        'counterparty_account_id' => null,
        'category_id' => $category->id,
        'amount' => 100,
        'description' => 'Старая',
        'frequency' => 'weekly',
        'next_occurred_on' => now()->toDateString(),
    ]);
    (new DeactivateRecurrence)->execute($inactive);

    $today = now()->toDateString();

    $this->actingAs($user)
        ->get(route('overview'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Overview')
            ->where('total', 1700)
            ->where('hasAccounts', true)
            ->has('expenseLine', now()->daysInMonth)
            ->where('expenseLine', fn ($line) => collect($line)->contains(
                fn (array $point): bool => $point['date'] === $today && $point['amount'] === 300,
            ))
            ->has('limits', 1)
            ->where('limits.0.spent', 300)
            ->where('limits.0.amount', 1000)
            ->where('limits.0.exceeded', false)
            ->has('goals', 1)
            ->where('goals.0.name', 'Отпуск')
            ->where('goals.0.progress', 0)
            ->has('debts', 1)
            ->where('debts.0.remainder', 4000)
            ->has('upcomingRecurrences', 1)
            ->where('upcomingRecurrences.0.id', $active->id)
            ->where('upcomingRecurrences.0.next_occurred_on', $active->next_occurred_on->toDateString()));
});

it('redirects the dashboard to the overview', function () {
    $user = User::factory()->create();
    (new CreateWorkspace)->execute($user, 'Семья', 'RUB');

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertRedirect(route('overview'));
});
