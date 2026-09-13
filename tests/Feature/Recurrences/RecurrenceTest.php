<?php

use App\Actions\Accounts\CreateAccount;
use App\Actions\Recurrences\CreateRecurrence;
use App\Actions\Recurrences\DeactivateRecurrence;
use App\Actions\Recurrences\PostRecurrence;
use App\Actions\Recurrences\UpdateRecurrence;
use App\Actions\Workspaces\CreateWorkspace;
use App\Models\User;
use Illuminate\Validation\ValidationException;
use Inertia\Testing\AssertableInertia;

it('posts the next occurrence and advances the date by a month', function () {
    $user = User::factory()->create();
    $ws = (new CreateWorkspace)->execute($user, 'Семья', 'RUB');
    $account = (new CreateAccount)->execute($ws, $user, ['name' => 'A', 'type' => 'checking', 'bank_id' => null, 'user_id' => null, 'opening_balance' => 0]);
    $cat = $ws->categories()->where('kind', 'expense')->first();
    $recurrence = (new CreateRecurrence)->execute($ws, [
        'type' => 'expense',
        'account_id' => $account->id,
        'counterparty_account_id' => null,
        'category_id' => $cat->id,
        'amount' => 500,
        'description' => 'Подписка',
        'frequency' => 'monthly',
        'next_occurred_on' => '2026-09-01',
    ]);

    $tx = (new PostRecurrence)->execute($recurrence, $user);

    expect($tx->amount)->toBe(500)
        ->and($tx->recurrence_id)->toBe($recurrence->id)
        ->and($recurrence->fresh()->next_occurred_on->toDateString())->toBe('2026-10-01');
});

it('refuses to post an inactive template', function () {
    $user = User::factory()->create();
    $ws = (new CreateWorkspace)->execute($user, 'Семья', 'RUB');
    $account = (new CreateAccount)->execute($ws, $user, ['name' => 'A', 'type' => 'checking', 'bank_id' => null, 'user_id' => null, 'opening_balance' => 0]);
    $cat = $ws->categories()->where('kind', 'expense')->first();
    $recurrence = (new CreateRecurrence)->execute($ws, [
        'type' => 'expense',
        'account_id' => $account->id,
        'counterparty_account_id' => null,
        'category_id' => $cat->id,
        'amount' => 500,
        'description' => 'Подписка',
        'frequency' => 'monthly',
        'next_occurred_on' => '2026-09-01',
    ]);

    (new DeactivateRecurrence)->execute($recurrence);

    (new PostRecurrence)->execute($recurrence->fresh(), $user);
})->throws(ValidationException::class);

it('posts a transfer from a transfer template', function () {
    $user = User::factory()->create();
    $ws = (new CreateWorkspace)->execute($user, 'Семья', 'RUB');
    $from = (new CreateAccount)->execute($ws, $user, ['name' => 'A', 'type' => 'checking', 'bank_id' => null, 'user_id' => null, 'opening_balance' => 0]);
    $to = (new CreateAccount)->execute($ws, $user, ['name' => 'B', 'type' => 'checking', 'bank_id' => null, 'user_id' => null, 'opening_balance' => 0]);
    $recurrence = (new CreateRecurrence)->execute($ws, [
        'type' => 'transfer',
        'account_id' => $from->id,
        'counterparty_account_id' => $to->id,
        'category_id' => null,
        'amount' => 700,
        'description' => 'На копилку',
        'frequency' => 'weekly',
        'next_occurred_on' => '2026-09-01',
    ]);

    $tx = (new PostRecurrence)->execute($recurrence, $user);

    expect($tx->type->value)->toBe('transfer')
        ->and($tx->amount)->toBe(700)
        ->and($tx->account_id)->toBe($from->id)
        ->and($tx->counterparty_account_id)->toBe($to->id)
        ->and($tx->recurrence_id)->toBe($recurrence->id)
        ->and($recurrence->fresh()->next_occurred_on->toDateString())->toBe('2026-09-08');
});

it('updates a template and deactivates it', function () {
    $user = User::factory()->create();
    $ws = (new CreateWorkspace)->execute($user, 'Семья', 'RUB');
    $account = (new CreateAccount)->execute($ws, $user, ['name' => 'A', 'type' => 'checking', 'bank_id' => null, 'user_id' => null, 'opening_balance' => 0]);
    $expense = $ws->categories()->where('kind', 'expense')->first();
    $income = $ws->categories()->where('kind', 'income')->first();
    $recurrence = (new CreateRecurrence)->execute($ws, [
        'type' => 'expense',
        'account_id' => $account->id,
        'counterparty_account_id' => null,
        'category_id' => $expense->id,
        'amount' => 500,
        'description' => 'Подписка',
        'frequency' => 'monthly',
        'next_occurred_on' => '2026-09-01',
    ]);

    (new UpdateRecurrence)->execute($recurrence, [
        'type' => 'income',
        'account_id' => $account->id,
        'counterparty_account_id' => null,
        'category_id' => $income->id,
        'amount' => 900,
        'description' => 'Зарплата',
        'frequency' => 'yearly',
        'next_occurred_on' => '2026-10-01',
    ]);

    $recurrence->refresh();

    expect($recurrence->type->value)->toBe('income')
        ->and($recurrence->category_id)->toBe($income->id)
        ->and($recurrence->amount)->toBe(900)
        ->and($recurrence->frequency->value)->toBe('yearly')
        ->and($recurrence->next_occurred_on->toDateString())->toBe('2026-10-01');

    (new DeactivateRecurrence)->execute($recurrence);

    expect($recurrence->fresh()->is_active)->toBeFalse();
});

it('creates a template via http, posts the next occurrence, and lists it', function () {
    $user = User::factory()->create();
    $ws = (new CreateWorkspace)->execute($user, 'Семья', 'RUB');
    $account = (new CreateAccount)->execute($ws, $user, [
        'name' => 'A',
        'type' => 'checking',
        'bank_id' => null,
        'user_id' => null,
        'opening_balance' => 0,
    ]);
    $category = $ws->categories()->where('kind', 'expense')->first();

    $this->actingAs($user)
        ->from(route('recurrences.index'))
        ->post(route('recurrences.store'), [
            'type' => 'expense',
            'account_id' => $account->id,
            'counterparty_account_id' => '',
            'category_id' => $category->id,
            'amount' => '5.00',
            'description' => 'Подписка',
            'frequency' => 'monthly',
            'next_occurred_on' => '2026-09-01',
        ])
        ->assertRedirect(route('recurrences.index'));

    $recurrence = $ws->recurrences()->firstOrFail();

    expect($recurrence->amount)->toBe(500)
        ->and($recurrence->is_active)->toBeTrue();

    $this->actingAs($user)
        ->from(route('recurrences.index'))
        ->post(route('recurrences.post', $recurrence))
        ->assertRedirect(route('recurrences.index'))
        ->assertSessionHasNoErrors();

    $recurrence->refresh();

    expect($recurrence->transactions()->count())->toBe(1)
        ->and($recurrence->next_occurred_on->toDateString())->toBe('2026-10-01');

    $this->actingAs($user)
        ->from(route('recurrences.index'))
        ->patch(route('recurrences.update', $recurrence), [
            'type' => 'expense',
            'account_id' => $account->id,
            'counterparty_account_id' => '',
            'category_id' => $category->id,
            'amount' => '7.00',
            'description' => 'Подписка плюс',
            'frequency' => 'monthly',
            'next_occurred_on' => '2026-10-01',
        ])
        ->assertRedirect(route('recurrences.index'))
        ->assertSessionHasNoErrors();

    expect($recurrence->fresh()->amount)->toBe(700);

    $this->withoutVite()
        ->get(route('recurrences.index'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('recurrences/Index')
            ->has('recurrences', 1)
            ->where('recurrences.0.amount', 700)
            ->where('recurrences.0.description', 'Подписка плюс')
            ->where('recurrences.0.is_active', true)
        );

    $this->actingAs($user)
        ->from(route('recurrences.index'))
        ->post(route('recurrences.deactivate', $recurrence))
        ->assertRedirect(route('recurrences.index'));

    expect($recurrence->fresh()->is_active)->toBeFalse();
});

it('hides another workspace template on index and write', function () {
    $a = User::factory()->create();
    $b = User::factory()->create();
    (new CreateWorkspace)->execute($a, 'A', 'RUB');
    $wsB = (new CreateWorkspace)->execute($b, 'B', 'RUB');
    $accountB = (new CreateAccount)->execute($wsB, $b, ['name' => 'Чужая', 'type' => 'checking', 'bank_id' => null, 'user_id' => null, 'opening_balance' => 0]);
    $catB = $wsB->categories()->where('kind', 'expense')->first();
    $recurrenceB = (new CreateRecurrence)->execute($wsB, [
        'type' => 'expense',
        'account_id' => $accountB->id,
        'counterparty_account_id' => null,
        'category_id' => $catB->id,
        'amount' => 500,
        'description' => 'Чужой',
        'frequency' => 'monthly',
        'next_occurred_on' => '2026-09-01',
    ]);

    $this->withoutVite()
        ->actingAs($a)
        ->get(route('recurrences.index'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('recurrences/Index')
            ->has('recurrences', 0)
        );

    $this->actingAs($a)
        ->post(route('recurrences.post', $recurrenceB))
        ->assertForbidden();
});
