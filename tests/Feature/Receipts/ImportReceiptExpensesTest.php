<?php

use App\Actions\Accounts\CreateAccount;
use App\Actions\Workspaces\CreateWorkspace;
use App\Models\User;
use App\Support\AccountBalance;
use Inertia\Testing\AssertableInertia;

beforeEach(function () {
    $this->withoutVite();
});

it('includes accounts and expense categories on the receipt page', function () {
    $user = User::factory()->create();
    $workspace = (new CreateWorkspace)->execute($user, 'Семья', 'RUB');
    (new CreateAccount)->execute($workspace, $user, [
        'name' => 'Карта',
        'type' => 'checking',
        'bank_id' => null,
        'user_id' => null,
        'opening_balance' => 0,
    ]);

    $this->actingAs($user)
        ->get(route('receipts.index'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('receipts/Index')
            ->has('accounts', 1)
            ->where('accounts.0.name', 'Карта')
            ->has('expenseCategories', 7));
});

it('imports selected receipt lines as expenses from one account', function () {
    $user = User::factory()->create();
    $workspace = (new CreateWorkspace)->execute($user, 'Семья', 'RUB');
    $account = (new CreateAccount)->execute($workspace, $user, [
        'name' => 'Карта',
        'type' => 'checking',
        'bank_id' => null,
        'user_id' => null,
        'opening_balance' => 500000,
    ]);
    $clothes = $workspace->categories()->where('kind', 'expense')->where('name', 'Одежда')->first();
    $food = $workspace->categories()->where('kind', 'expense')->where('name', 'Продукты')->first();

    $this->actingAs($user)
        ->from(route('receipts.index'))
        ->withSession(['receipt' => ['seller' => 'ООО ФАКТОР']])
        ->post(route('receipts.import'), [
            'account_id' => $account->id,
            'occurred_on' => '2026-09-15',
            'items' => [
                [
                    'name' => 'Футболка',
                    'amount' => 99900,
                    'category_id' => $clothes->id,
                ],
                [
                    'name' => 'Пакет Zolla',
                    'amount' => 500,
                    'category_id' => $clothes->id,
                ],
            ],
        ])
        ->assertRedirect(route('transactions.index'))
        ->assertSessionHasNoErrors()
        ->assertSessionMissing('receipt');

    $expenses = $workspace->transactions()->where('type', 'expense')->orderBy('id')->get();

    expect($expenses)->toHaveCount(2)
        ->and(AccountBalance::for($account->fresh()))->toBe(500000 - 99900 - 500)
        ->and($expenses->pluck('description')->all())->toBe(['Футболка', 'Пакет Zolla'])
        ->and($expenses[0]->category_id)->toBe($clothes->id);

    expect($food)->not->toBeNull();
});

it('suggests a category for each receipt line', function () {
    $user = User::factory()->create();
    $workspace = (new CreateWorkspace)->execute($user, 'Семья', 'RUB');
    $clothes = $workspace->categories()->where('kind', 'expense')->where('name', 'Одежда')->first();

    $this->actingAs($user)
        ->withSession([
            'receipt' => [
                'qr' => 't=20260915T1507&s=1004.00&fn=1&i=1&fp=1&n=1',
                'fiscal' => [
                    't' => '20260915T1507',
                    's' => '1004.00',
                    'fn' => '1',
                    'i' => '1',
                    'fp' => '1',
                    'n' => '1',
                ],
                'seller' => 'ООО ФАКТОР',
                'inn' => '1',
                'datetime' => '2026-09-15T15:07:00+03:00',
                'total' => 100400,
                'items' => [
                    ['name' => 'Футболка', 'quantity' => '1', 'price' => 99900, 'sum' => 99900],
                ],
            ],
        ])
        ->get(route('receipts.index'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('receipt.items.0.suggested_category_id', $clothes->id)
            ->where('receipt.occurred_on', '2026-09-15'));
});

it('rejects import without an account', function () {
    $user = User::factory()->create();
    $workspace = (new CreateWorkspace)->execute($user, 'Семья', 'RUB');
    $clothes = $workspace->categories()->where('kind', 'expense')->where('name', 'Одежда')->first();

    $this->actingAs($user)
        ->from(route('receipts.index'))
        ->post(route('receipts.import'), [
            'occurred_on' => '2026-09-15',
            'items' => [
                [
                    'name' => 'Футболка',
                    'amount' => 99900,
                    'category_id' => $clothes->id,
                ],
            ],
        ])
        ->assertRedirect(route('receipts.index'))
        ->assertSessionHasErrors('account_id');
});
