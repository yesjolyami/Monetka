<?php

use App\Actions\Invitations\AcceptInvitation;
use App\Actions\Invitations\SendInvitation;
use App\Actions\Workspaces\CreateWorkspace;
use App\Enums\WorkspaceRole;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;

it('lets the owner invite by email and accept as member', function () {
    Notification::fake();
    $owner = User::factory()->create();
    $invitee = User::factory()->create(['email' => 'anna@example.com']);
    $workspace = (new CreateWorkspace)->execute($owner, 'Семья', 'RUB');

    $invitation = (new SendInvitation)->execute($owner, $workspace, 'anna@example.com');

    (new AcceptInvitation)->execute($invitee, $invitation);

    expect($workspace->memberships()->where('user_id', $invitee->id)->first()->role)
        ->toBe(WorkspaceRole::Member);
});

it('forbids a member from inviting', function () {
    $owner = User::factory()->create();
    $member = User::factory()->create();
    $workspace = (new CreateWorkspace)->execute($owner, 'Семья', 'RUB');
    $workspace->memberships()->create(['user_id' => $member->id, 'role' => 'member']);

    (new SendInvitation)->execute($member, $workspace, 'new@example.com');
})->throws(AuthorizationException::class);

it('rejects accepting with a different email', function () {
    $owner = User::factory()->create();
    $stranger = User::factory()->create(['email' => 'other@example.com']);
    $workspace = (new CreateWorkspace)->execute($owner, 'Семья', 'RUB');
    $invitation = (new SendInvitation)->execute($owner, $workspace, 'anna@example.com');

    (new AcceptInvitation)->execute($stranger, $invitation);
})->throws(ValidationException::class);

it('replaces a previous pending invite for the same email', function () {
    $owner = User::factory()->create();
    $workspace = (new CreateWorkspace)->execute($owner, 'Семья', 'RUB');
    $first = (new SendInvitation)->execute($owner, $workspace, 'anna@example.com');
    $second = (new SendInvitation)->execute($owner, $workspace, 'anna@example.com');

    expect($first->fresh()->expires_at->lt(now()))->toBeTrue()
        ->and($second->token)->not->toBe($first->token);
});

it('cannot invite an existing member', function () {
    $owner = User::factory()->create();
    $member = User::factory()->create(['email' => 'anna@example.com']);
    $workspace = (new CreateWorkspace)->execute($owner, 'Семья', 'RUB');
    $workspace->memberships()->create(['user_id' => $member->id, 'role' => 'member']);

    (new SendInvitation)->execute($owner, $workspace, 'anna@example.com');
})->throws(ValidationException::class);

it('lets a user with no workspace accept an invite without being sent to create', function () {
    $owner = User::factory()->create();
    $invitee = User::factory()->create(['email' => 'anna@example.com']);
    $workspace = (new CreateWorkspace)->execute($owner, 'Семья', 'RUB');
    $invitation = (new SendInvitation)->execute($owner, $workspace, 'anna@example.com');

    $this->actingAs($invitee)
        ->get(route('invitations.show', $invitation->token))
        ->assertOk();

    $this->actingAs($invitee)
        ->post(route('invitations.accept', $invitation->token))
        ->assertRedirect();
});
