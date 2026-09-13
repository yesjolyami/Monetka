<?php

namespace App\Actions\Workspaces;

use App\Models\User;
use App\Models\Workspace;
use Illuminate\Support\Facades\Gate;

class SwitchWorkspace
{
    public function execute(User $user, Workspace $workspace): void
    {
        Gate::forUser($user)->authorize('view', $workspace);

        $user->forceFill(['current_workspace_id' => $workspace->id])->save();
    }
}
