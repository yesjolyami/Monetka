<?php

namespace App\Actions\Workspaces;

use App\Enums\WorkspaceRole;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Support\Facades\DB;

class CreateWorkspace
{
    public function execute(User $user, string $name, string $currency): Workspace
    {
        return DB::transaction(function () use ($user, $name, $currency) {
            $workspace = Workspace::query()->create([
                'name' => $name,
                'currency' => $currency,
            ]);

            $workspace->memberships()->create([
                'user_id' => $user->id,
                'role' => WorkspaceRole::Owner,
            ]);

            $user->forceFill(['current_workspace_id' => $workspace->id])->save();

            return $workspace;
        });
    }
}
