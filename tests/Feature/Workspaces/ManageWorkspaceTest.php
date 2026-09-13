<?php

use App\Actions\Accounts\CreateAccount;
use App\Actions\Banks\CreateBank;
use App\Actions\Invitations\SendInvitation;
use App\Actions\Transactions\RecordIncome;
use App\Actions\Workspaces\CreateWorkspace;
use App\Actions\Workspaces\DeleteWorkspace;
use App\Actions\Workspaces\LeaveWorkspace;
use App\Actions\Workspaces\RemoveMember;
use App\Actions\Workspaces\TransferOwnership;
use App\Actions\Workspaces\UpdateWorkspace;
use App\Enums\WorkspaceRole;
use App\Models\Account;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Validation\ValidationException;
use Inertia\Testing\AssertableInertia;

it('blocks currency change after operations exist', function () {
    $user = User::factory()->create();
    $ws = (new CreateWorkspace)->execute($user, 'Семья', 'RUB');
    Account::factory()->create(['workspace_id' => $ws->id]);
    Transaction::factory()->create(['workspace_id' => $ws->id, 'account_id' => $ws->accounts()->first()->id, 'amount' => 1, 'type' => 'income']);

    (new UpdateWorkspace)->execute($user, $ws, ['name' => 'Семья', 'currency' => 'USD']);
})->throws(ValidationException::class);

it('transfers ownership', function () {
    $owner = User::factory()->create();
    $member = User::factory()->create();
    $ws = (new CreateWorkspace)->execute($owner, 'Семья', 'RUB');
    $ws->memberships()->create(['user_id' => $member->id, 'role' => WorkspaceRole::Member]);

    (new TransferOwnership)->execute($owner, $ws, $member);

    expect($ws->roleFor($member->fresh()))->toBe(WorkspaceRole::Owner)
        ->and($ws->roleFor($owner->fresh()))->toBe(WorkspaceRole::Member);
});

it('prevents the owner from leaving', function () {
    $owner = User::factory()->create();
    $ws = (new CreateWorkspace)->execute($owner, 'Семья', 'RUB');
    (new LeaveWorkspace)->execute($owner, $ws);
})->throws(ValidationException::class);

it('returns 422 when currency changes after operations exist', function () {
    $user = User::factory()->create();
    $ws = (new CreateWorkspace)->execute($user, 'Семья', 'RUB');
    Account::factory()->create(['workspace_id' => $ws->id]);
    Transaction::factory()->create([
        'workspace_id' => $ws->id,
        'account_id' => $ws->accounts()->first()->id,
        'amount' => 1,
        'type' => 'income',
    ]);

    $this->actingAs($user)
        ->patch(route('workspaces.update'), ['name' => 'Семья', 'currency' => 'USD'], [
            'Accept' => 'application/json',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('currency');
});

it('lets a member rename the workspace', function () {
    $owner = User::factory()->create();
    $member = User::factory()->create();
    $ws = (new CreateWorkspace)->execute($owner, 'Семья', 'RUB');
    $ws->memberships()->create(['user_id' => $member->id, 'role' => WorkspaceRole::Member]);
    $member->forceFill(['current_workspace_id' => $ws->id])->save();

    $this->actingAs($member)
        ->patch(route('workspaces.update'), ['name' => 'Дом', 'currency' => 'RUB'])
        ->assertRedirect();

    expect($ws->fresh()->name)->toBe('Дом')
        ->and($ws->fresh()->currency)->toBe('RUB');
});

it('forbids a member from deleting the workspace', function () {
    $owner = User::factory()->create();
    $member = User::factory()->create();
    $ws = (new CreateWorkspace)->execute($owner, 'Семья', 'RUB');
    $ws->memberships()->create(['user_id' => $member->id, 'role' => WorkspaceRole::Member]);
    $member->forceFill(['current_workspace_id' => $ws->id])->save();

    $this->actingAs($member)
        ->delete(route('workspaces.destroy'), ['confirm' => '1'])
        ->assertForbidden();

    $this->assertDatabaseHas('workspaces', ['id' => $ws->id]);
});

it('redirects to create after leaving the last workspace', function () {
    $owner = User::factory()->create();
    $member = User::factory()->create();
    $ws = (new CreateWorkspace)->execute($owner, 'Семья', 'RUB');
    $ws->memberships()->create(['user_id' => $member->id, 'role' => WorkspaceRole::Member]);
    $member->forceFill(['current_workspace_id' => $ws->id])->save();

    $this->actingAs($member)
        ->post(route('workspaces.leave'))
        ->assertRedirect(route('workspaces.create'));

    expect($ws->fresh()->hasMember($member->fresh()))->toBeFalse()
        ->and($member->fresh()->current_workspace_id)->toBeNull();
});

it('renders workspace settings with people', function () {
    $owner = User::factory()->create(['name' => 'Оля']);
    $member = User::factory()->create(['name' => 'Анна', 'email' => 'anna@example.com']);
    $ws = (new CreateWorkspace)->execute($owner, 'Семья', 'RUB');
    $ws->memberships()->create(['user_id' => $member->id, 'role' => WorkspaceRole::Member]);
    (new SendInvitation)->execute($owner, $ws, 'invitee@example.com');

    $this->actingAs($owner)
        ->withoutVite()
        ->get(route('workspaces.settings'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('workspaces/Settings')
            ->where('workspaceName', 'Семья')
            ->where('name', config('app.name'))
            ->where('currency', 'RUB')
            ->where('hasTransactions', false)
            ->has('members', 2)
            ->has('invitations', 1)
            ->where('invitations.0.email', 'invitee@example.com'));
});

it('imports inertia Link on the workspace settings page', function () {
    $source = file_get_contents(resource_path('js/pages/workspaces/Settings.vue'));

    expect($source)
        ->toContain("from '@inertiajs/vue3'")
        ->toContain('import { Form, Head, Link, usePage }');
});

it('deletes a workspace with a ledger without touching another budget', function () {
    $owner = User::factory()->create();
    $stranger = User::factory()->create();
    $ws = (new CreateWorkspace)->execute($owner, 'Семья', 'RUB');
    $other = (new CreateWorkspace)->execute($stranger, 'Чужой', 'USD');
    $bank = (new CreateBank)->execute($ws, ['name' => 'Тинькофф']);
    $account = (new CreateAccount)->execute($ws, $owner, [
        'name' => 'Карта',
        'type' => 'checking',
        'bank_id' => $bank->id,
        'user_id' => $owner->id,
        'opening_balance' => 0,
    ]);
    $category = $ws->categories()->where('kind', 'income')->first();
    $transaction = (new RecordIncome)->execute($ws, $owner, [
        'account_id' => $account->id,
        'category_id' => $category->id,
        'amount' => 100,
        'occurred_on' => '2026-09-01',
        'description' => 'x',
    ]);

    (new DeleteWorkspace)->execute($owner, $ws);

    $this->assertDatabaseMissing('workspaces', ['id' => $ws->id]);
    $this->assertDatabaseMissing('accounts', ['id' => $account->id]);
    $this->assertDatabaseMissing('transactions', ['id' => $transaction->id]);
    $this->assertDatabaseMissing('banks', ['id' => $bank->id]);
    $this->assertDatabaseHas('workspaces', ['id' => $other->id, 'name' => 'Чужой']);
    expect($other->fresh()->categories()->exists())->toBeTrue()
        ->and($stranger->fresh()->current_workspace_id)->toBe($other->id)
        ->and($owner->fresh()->current_workspace_id)->toBeNull();
});

it('deletes the workspace when the owner confirms', function () {
    $owner = User::factory()->create();
    $ws = (new CreateWorkspace)->execute($owner, 'Семья', 'RUB');

    $this->actingAs($owner)
        ->delete(route('workspaces.destroy'), ['confirm' => '1'])
        ->assertRedirect(route('workspaces.create'));

    $this->assertDatabaseMissing('workspaces', ['id' => $ws->id]);
    expect($owner->fresh()->current_workspace_id)->toBeNull();
});

it('lets the owner transfer ownership via http', function () {
    $owner = User::factory()->create();
    $member = User::factory()->create();
    $ws = (new CreateWorkspace)->execute($owner, 'Семья', 'RUB');
    $ws->memberships()->create(['user_id' => $member->id, 'role' => WorkspaceRole::Member]);

    $this->actingAs($owner)
        ->post(route('workspaces.transfer'), ['user_id' => $member->id])
        ->assertRedirect();

    expect($ws->roleFor($member->fresh()))->toBe(WorkspaceRole::Owner)
        ->and($ws->roleFor($owner->fresh()))->toBe(WorkspaceRole::Member);
});

it('lets the owner remove a member', function () {
    $owner = User::factory()->create();
    $member = User::factory()->create();
    $ws = (new CreateWorkspace)->execute($owner, 'Семья', 'RUB');
    $ws->memberships()->create(['user_id' => $member->id, 'role' => WorkspaceRole::Member]);
    $member->forceFill(['current_workspace_id' => $ws->id])->save();

    (new RemoveMember)->execute($owner, $ws, $member);

    expect($ws->fresh()->hasMember($member->fresh()))->toBeFalse()
        ->and($member->fresh()->current_workspace_id)->toBeNull();
});

it('forbids the owner from removing themselves', function () {
    $owner = User::factory()->create();
    $ws = (new CreateWorkspace)->execute($owner, 'Семья', 'RUB');

    (new RemoveMember)->execute($owner, $ws, $owner);
})->throws(ValidationException::class);
