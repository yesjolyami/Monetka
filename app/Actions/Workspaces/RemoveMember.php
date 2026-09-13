<?php

namespace App\Actions\Workspaces;

use App\Enums\WorkspaceRole;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class RemoveMember
{
    public function execute(User $actor, Workspace $workspace, User $member): void
    {
        Gate::forUser($actor)->authorize('removeMember', $workspace);

        if ($member->is($actor)) {
            throw ValidationException::withMessages([
                'member' => 'Нельзя исключить себя.',
            ]);
        }

        if ($workspace->roleFor($member) === WorkspaceRole::Owner) {
            throw ValidationException::withMessages([
                'member' => 'Нельзя исключить владельца.',
            ]);
        }

        if (! $workspace->hasMember($member)) {
            throw ValidationException::withMessages([
                'member' => 'Пользователь не состоит в бюджете.',
            ]);
        }

        DB::transaction(function () use ($workspace, $member): void {
            $workspace->memberships()->where('user_id', $member->id)->delete();

            if ($member->current_workspace_id === $workspace->id) {
                $member->forceFill(['current_workspace_id' => null])->save();
            }
        });
    }
}
