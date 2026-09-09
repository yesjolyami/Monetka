<?php

use App\Actions\Workspaces\CreateWorkspace;
use App\Actions\Workspaces\SwitchWorkspace;
use App\Enums\WorkspaceRole;
use App\Models\User;
use App\Models\Workspace;

it('creates a workspace and makes the user the owner', function () {
    $user = User::factory()->create();

    $workspace = (new CreateWorkspace)->execute($user, 'Семья', 'RUB');

    expect($workspace->currency)->toBe('RUB')
        ->and($user->fresh()->current_workspace_id)->toBe($workspace->id)
        ->and($workspace->memberships()->where('user_id', $user->id)->first()->role)
        ->toBe(WorkspaceRole::Owner);
});

it('stores the workspace via http', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('workspaces.store'), ['name' => 'Семья', 'currency' => 'RUB'])
        ->assertRedirect();

    $this->assertDatabaseHas('workspaces', ['name' => 'Семья', 'currency' => 'RUB']);
});

it('does not switch to a workspace the user does not belong to', function () {
    $user = User::factory()->create();
    $other = User::factory()->create();
    $workspace = (new CreateWorkspace)->execute($other, 'Чужой', 'USD');

    expect(fn () => (new SwitchWorkspace)->execute($user, $workspace))
        ->toThrow(Illuminate\Auth\Access\AuthorizationException::class);
});
