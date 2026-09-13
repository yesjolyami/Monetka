<?php

use App\Actions\Accounts\CreateAccount;
use App\Actions\Transactions\RecordExpense;
use App\Actions\Transactions\RecordIncome;
use App\Actions\Workspaces\CreateWorkspace;
use App\Models\User;
use Inertia\Testing\AssertableInertia;

it('exports csv with a bom and the expense row', function () {
    $user = User::factory()->create();
    $ws = (new CreateWorkspace)->execute($user, 'Семья', 'RUB');
    $account = (new CreateAccount)->execute($ws, $user, ['name' => 'Карта', 'type' => 'checking', 'bank_id' => null, 'user_id' => null, 'opening_balance' => 0]);
    $cat = $ws->categories()->where('kind', 'expense')->first();
    (new RecordExpense)->execute($ws, $user, [
        'account_id' => $account->id, 'category_id' => $cat->id, 'amount' => 2500,
        'occurred_on' => '2026-09-02', 'description' => 'Магнит',
    ]);

    $response = $this->actingAs($user)->get(route('export.csv', ['month' => '2026-09']));
    $response->assertOk();
    $body = $response->streamedContent();
    expect(str_starts_with($body, "\xEF\xBB\xBF"))->toBeTrue()
        ->and($body)->toContain('Магнит')
        ->and($body)->toContain('25.00');
});

it('sends csv headers and a month filename', function () {
    $user = User::factory()->create();
    (new CreateWorkspace)->execute($user, 'Семья', 'RUB');

    $response = $this->actingAs($user)->get(route('export.csv', ['month' => '2026-09']));

    $response->assertOk()
        ->assertHeader('Content-Type', 'text/csv; charset=UTF-8');

    expect($response->headers->get('Content-Disposition'))
        ->toContain('attachment')
        ->toContain('monetka-operations-2026-09.csv');

    $body = $response->streamedContent();
    expect($body)->toContain('Дата,Тип,Сумма,Валюта,Счёт,Счёт-получатель,Категория,Описание');
});

it('exports every month when month is all', function () {
    $user = User::factory()->create();
    $ws = (new CreateWorkspace)->execute($user, 'Семья', 'RUB');
    $account = (new CreateAccount)->execute($ws, $user, [
        'name' => 'Карта',
        'type' => 'checking',
        'bank_id' => null,
        'user_id' => null,
        'opening_balance' => 0,
    ]);
    $income = $ws->categories()->where('kind', 'income')->first();
    $expense = $ws->categories()->where('kind', 'expense')->first();

    (new RecordIncome)->execute($ws, $user, [
        'account_id' => $account->id,
        'category_id' => $income->id,
        'amount' => 10000,
        'occurred_on' => '2026-08-01',
        'description' => 'Август',
    ]);
    (new RecordExpense)->execute($ws, $user, [
        'account_id' => $account->id,
        'category_id' => $expense->id,
        'amount' => 2500,
        'occurred_on' => '2026-09-02',
        'description' => 'Магнит',
    ]);

    $september = $this->actingAs($user)->get(route('export.csv', ['month' => '2026-09']));
    $septemberBody = $september->streamedContent();

    expect($september->headers->get('Content-Disposition'))
        ->toContain('monetka-operations-2026-09.csv')
        ->and($septemberBody)->toContain('Магнит')
        ->and($septemberBody)->not->toContain('Август')
        ->and($septemberBody)->toContain('Расход')
        ->and($septemberBody)->toContain('RUB')
        ->and($septemberBody)->toContain('2026-09-02');

    $all = $this->actingAs($user)->get(route('export.csv', ['month' => 'all']));
    $allBody = $all->streamedContent();

    expect($all->headers->get('Content-Disposition'))
        ->toContain('monetka-operations-all.csv')
        ->and($allBody)->toContain('Магнит')
        ->and($allBody)->toContain('Август')
        ->and($allBody)->toContain('Доход');
});

it('lists every month on the operations page when month is all', function () {
    $user = User::factory()->create();
    $ws = (new CreateWorkspace)->execute($user, 'Семья', 'RUB');
    $account = (new CreateAccount)->execute($ws, $user, [
        'name' => 'Карта',
        'type' => 'checking',
        'bank_id' => null,
        'user_id' => null,
        'opening_balance' => 0,
    ]);
    $income = $ws->categories()->where('kind', 'income')->first();
    $expense = $ws->categories()->where('kind', 'expense')->first();

    (new RecordIncome)->execute($ws, $user, [
        'account_id' => $account->id,
        'category_id' => $income->id,
        'amount' => 10000,
        'occurred_on' => '2026-08-01',
        'description' => 'Август',
    ]);
    (new RecordExpense)->execute($ws, $user, [
        'account_id' => $account->id,
        'category_id' => $expense->id,
        'amount' => 2500,
        'occurred_on' => '2026-09-02',
        'description' => 'Магнит',
    ]);

    $this->actingAs($user)
        ->withoutVite()
        ->get(route('transactions.index', ['month' => 'all']))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('transactions/Index')
            ->where('filters.month', 'all')
            ->has('transactions', 2)
        );
});

it('redirects guests to login', function () {
    $this->get(route('export.csv', ['month' => '2026-09']))
        ->assertRedirect(route('login'));
});
