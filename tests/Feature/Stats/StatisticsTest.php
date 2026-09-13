<?php

use App\Actions\Accounts\CreateAccount;
use App\Actions\Accounts\SetAccountBalance;
use App\Actions\Transactions\RecordExpense;
use App\Actions\Transactions\RecordIncome;
use App\Actions\Workspaces\CreateWorkspace;
use App\Models\User;
use App\Support\WorkspaceStatistics;
use Illuminate\Support\Carbon;
use Inertia\Testing\AssertableInertia;

beforeEach(function () {
    $this->withoutVite();
});

it('ignores adjustments in expense totals', function () {
    $user = User::factory()->create();
    $ws = (new CreateWorkspace)->execute($user, 'Семья', 'RUB');
    $account = (new CreateAccount)->execute($ws, $user, ['name' => 'A', 'type' => 'checking', 'bank_id' => null, 'user_id' => null, 'opening_balance' => 0]);
    $cat = $ws->categories()->where('kind', 'expense')->first();
    (new RecordExpense)->execute($ws, $user, [
        'account_id' => $account->id, 'category_id' => $cat->id, 'amount' => 400,
        'occurred_on' => '2026-09-02', 'description' => 'еда',
    ]);
    (new SetAccountBalance)->execute($account, $user, 0, '2026-09-03');

    $stats = WorkspaceStatistics::for($ws, 2026, 9);
    expect($stats['expense'])->toBe(400);
});

it('forecasts using previous months when available', function () {
    Carbon::setTestNow('2026-09-10');
    $user = User::factory()->create();
    $ws = (new CreateWorkspace)->execute($user, 'Семья', 'RUB');
    $account = (new CreateAccount)->execute($ws, $user, ['name' => 'A', 'type' => 'checking', 'bank_id' => null, 'user_id' => null, 'opening_balance' => 0]);
    $cat = $ws->categories()->where('kind', 'expense')->first();
    // August has 31 days, 3100 spent => 100/day
    (new RecordExpense)->execute($ws, $user, [
        'account_id' => $account->id, 'category_id' => $cat->id, 'amount' => 3100,
        'occurred_on' => '2026-08-15', 'description' => 'август',
    ]);
    (new RecordExpense)->execute($ws, $user, [
        'account_id' => $account->id, 'category_id' => $cat->id, 'amount' => 500,
        'occurred_on' => '2026-09-05', 'description' => 'сентябрь',
    ]);

    $stats = WorkspaceStatistics::for($ws, 2026, 9);
    $remaining = 30 - 10;
    expect($stats['forecast'])->toBe(500 + (int) round((3100 / 31) * $remaining));
    Carbon::setTestNow();
});

it('compares months ranks top expenses and hides forecast for a past month', function () {
    Carbon::setTestNow('2026-09-10');
    $user = User::factory()->create();
    $ws = (new CreateWorkspace)->execute($user, 'Семья', 'RUB');
    $account = (new CreateAccount)->execute($ws, $user, ['name' => 'A', 'type' => 'checking', 'bank_id' => null, 'user_id' => null, 'opening_balance' => 0]);
    $income = $ws->categories()->where('kind', 'income')->first();
    $food = $ws->categories()->where('kind', 'expense')->where('name', 'Продукты')->first();
    $transport = $ws->categories()->where('kind', 'expense')->where('name', 'Транспорт')->first();
    $cafe = $ws->categories()->where('kind', 'expense')->where('name', 'Кафе')->first();

    (new RecordIncome)->execute($ws, $user, [
        'account_id' => $account->id, 'category_id' => $income->id, 'amount' => 2000,
        'occurred_on' => '2026-08-01', 'description' => 'август доход',
    ]);
    (new RecordIncome)->execute($ws, $user, [
        'account_id' => $account->id, 'category_id' => $income->id, 'amount' => 1500,
        'occurred_on' => '2026-09-01', 'description' => 'сентябрь доход',
    ]);
    (new RecordExpense)->execute($ws, $user, [
        'account_id' => $account->id, 'category_id' => $food->id, 'amount' => 800,
        'occurred_on' => '2026-08-10', 'description' => 'продукты август',
    ]);
    (new RecordExpense)->execute($ws, $user, [
        'account_id' => $account->id, 'category_id' => $food->id, 'amount' => 500,
        'occurred_on' => '2026-09-02', 'description' => 'продукты',
    ]);
    (new RecordExpense)->execute($ws, $user, [
        'account_id' => $account->id, 'category_id' => $transport->id, 'amount' => 300,
        'occurred_on' => '2026-09-03', 'description' => 'транспорт',
    ]);
    (new RecordExpense)->execute($ws, $user, [
        'account_id' => $account->id, 'category_id' => $cafe->id, 'amount' => 100,
        'occurred_on' => '2026-09-04', 'description' => 'кафе',
    ]);
    (new RecordExpense)->execute($ws, $user, [
        'account_id' => $account->id, 'category_id' => $food->id, 'amount' => 50,
        'occurred_on' => '2026-04-15', 'description' => 'апрель',
    ]);

    $stats = WorkspaceStatistics::for($ws, 2026, 9);

    expect($stats['income'])->toBe(1500)
        ->and($stats['expense'])->toBe(900)
        ->and($stats['previousIncome'])->toBe(2000)
        ->and($stats['previousExpense'])->toBe(800)
        ->and($stats['expenseByMonth'])->toHaveCount(6)
        ->and(array_column($stats['expenseByMonth'], 'label'))->toBe([
            '2026-04', '2026-05', '2026-06', '2026-07', '2026-08', '2026-09',
        ])
        ->and(array_column($stats['expenseByMonth'], 'amount'))->toBe([50, 0, 0, 0, 800, 900])
        ->and($stats['topExpenseCategories'])->toHaveCount(3)
        ->and($stats['topExpenseCategories'][0]['id'])->toBe($food->id)
        ->and($stats['topExpenseCategories'][0]['name'])->toBe('Продукты')
        ->and($stats['topExpenseCategories'][0]['emoji'])->toBe('🥑')
        ->and($stats['topExpenseCategories'][0]['amount'])->toBe(500)
        ->and($stats['topExpenseCategories'][1]['id'])->toBe($transport->id)
        ->and($stats['topExpenseCategories'][1]['amount'])->toBe(300)
        ->and($stats['topExpenseCategories'][2]['id'])->toBe($cafe->id)
        ->and($stats['topExpenseCategories'][2]['amount'])->toBe(100);

    $past = WorkspaceStatistics::for($ws, 2026, 8);
    expect($past['forecast'])->toBeNull()
        ->and($past['expense'])->toBe(800)
        ->and($past['income'])->toBe(2000);

    Carbon::setTestNow();
});

it('forecasts from the current month when there is no history', function () {
    Carbon::setTestNow('2026-09-10');
    $user = User::factory()->create();
    $ws = (new CreateWorkspace)->execute($user, 'Семья', 'RUB');
    $account = (new CreateAccount)->execute($ws, $user, ['name' => 'A', 'type' => 'checking', 'bank_id' => null, 'user_id' => null, 'opening_balance' => 0]);
    $cat = $ws->categories()->where('kind', 'expense')->first();
    (new RecordExpense)->execute($ws, $user, [
        'account_id' => $account->id, 'category_id' => $cat->id, 'amount' => 500,
        'occurred_on' => '2026-09-05', 'description' => 'сентябрь',
    ]);

    $stats = WorkspaceStatistics::for($ws, 2026, 9);
    $remaining = 30 - 10;
    expect($stats['forecast'])->toBe(500 + (int) round((500 / 10) * $remaining));
    Carbon::setTestNow();
});

it('renders the statistics page for a member', function () {
    Carbon::setTestNow('2026-09-10');
    $user = User::factory()->create();
    $ws = (new CreateWorkspace)->execute($user, 'Семья', 'RUB');
    $account = (new CreateAccount)->execute($ws, $user, ['name' => 'A', 'type' => 'checking', 'bank_id' => null, 'user_id' => null, 'opening_balance' => 0]);
    $cat = $ws->categories()->where('kind', 'expense')->first();
    (new RecordExpense)->execute($ws, $user, [
        'account_id' => $account->id, 'category_id' => $cat->id, 'amount' => 400,
        'occurred_on' => '2026-09-02', 'description' => 'еда',
    ]);

    $this->actingAs($user)
        ->get(route('stats.index', ['month' => '2026-09']))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('stats/Index')
            ->where('year', 2026)
            ->where('month', 9)
            ->where('stats.expense', 400)
            ->where('stats.income', 0)
            ->has('stats.expenseByMonth', 6)
            ->has('stats.topExpenseCategories')
            ->where('stats.forecast', fn ($forecast): bool => is_int($forecast)));

    Carbon::setTestNow();
});

it('renders the stats report root', function () {
    $user = User::factory()->create();
    (new CreateWorkspace)->execute($user, 'Семья', 'RUB');

    $this->actingAs($user)
        ->get(route('stats.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('stats/Index'));
});
