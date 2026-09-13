<?php

namespace App\Policies\Concerns;

use App\Models\User;
use App\Models\Workspace;
use Illuminate\Database\Eloquent\Model;

trait AuthorizesWorkspaceMembers
{
    public function create(User $user, Workspace $workspace): bool
    {
        return $user->belongsToWorkspace($workspace);
    }

    protected function allows(User $user, Model $record): bool
    {
        $workspaceId = $record->getAttribute('workspace_id');

        if (! is_numeric($workspaceId)) {
            return false;
        }

        $workspace = Workspace::query()->find((int) $workspaceId);

        return $workspace instanceof Workspace && $user->belongsToWorkspace($workspace);
    }
}
