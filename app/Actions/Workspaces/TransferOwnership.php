<?php

namespace App\Actions\Workspaces;

use App\Enums\WorkspaceRole;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class TransferOwnership
{
    public function execute(User $actor, Workspace $workspace, User $newOwner): void
    {
        Gate::forUser($actor)->authorize('transferOwnership', $workspace);

        if ($newOwner->is($actor)) {
            throw ValidationException::withMessages([
                'user_id' => 'Нельзя передать владение себе.',
            ]);
        }

        $actorMembership = $workspace->memberships()->where('user_id', $actor->id)->first();
        $targetMembership = $workspace->memberships()->where('user_id', $newOwner->id)->first();

        if ($actorMembership === null || $targetMembership === null) {
            throw ValidationException::withMessages([
                'user_id' => 'Пользователь не состоит в бюджете.',
            ]);
        }

        DB::transaction(function () use ($actorMembership, $targetMembership): void {
            $actorMembership->update(['role' => WorkspaceRole::Member]);
            $targetMembership->update(['role' => WorkspaceRole::Owner]);
        });
    }
}
