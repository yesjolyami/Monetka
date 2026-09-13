<?php

use App\Actions\Workspaces\CreateWorkspace;
use App\Models\User;

it('redirects authenticated users without a workspace to create', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertRedirect(route('workspaces.create'));
});

it('uses the first workspace when current membership is gone', function () {
    $user = User::factory()->create();
    $other = User::factory()->create();
    $workspace = (new CreateWorkspace)->execute($user, 'Основной', 'RUB');
    $foreign = (new CreateWorkspace)->execute($other, 'Чужой', 'USD');
    $user->forceFill(['current_workspace_id' => $foreign->id])->save();

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk();

    expect($user->fresh()->current_workspace_id)->toBe($workspace->id);
});
