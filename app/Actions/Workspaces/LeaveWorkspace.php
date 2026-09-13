<?php

namespace App\Actions\Workspaces;

use App\Enums\WorkspaceRole;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class LeaveWorkspace
{
    public function execute(User $actor, Workspace $workspace): void
    {
        Gate::forUser($actor)->authorize('view', $workspace);

        if ($workspace->roleFor($actor) === WorkspaceRole::Owner) {
            throw ValidationException::withMessages([
                'workspace' => 'Сначала передайте владение.',
            ]);
        }

        DB::transaction(function () use ($actor, $workspace): void {
            $workspace->memberships()->where('user_id', $actor->id)->delete();

            if ($actor->current_workspace_id === $workspace->id) {
                $actor->forceFill(['current_workspace_id' => null])->save();
            }
        });
    }
}
