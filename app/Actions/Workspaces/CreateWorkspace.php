<?php

namespace App\Actions\Workspaces;

use App\Enums\WorkspaceRole;
use App\Models\User;
use App\Models\Workspace;
use App\Support\DefaultCategories;
use Illuminate\Support\Facades\DB;

class CreateWorkspace
{
    public function execute(User $user, string $name, string $currency, bool $seedCategories = true): Workspace
    {
        return DB::transaction(function () use ($user, $name, $currency, $seedCategories) {
            $workspace = Workspace::query()->create([
                'name' => $name,
                'currency' => $currency,
            ]);

            $workspace->memberships()->create([
                'user_id' => $user->id,
                'role' => WorkspaceRole::Owner,
            ]);

            $user->forceFill(['current_workspace_id' => $workspace->id])->save();

            if ($seedCategories) {
                DefaultCategories::seed($workspace);
            }

            return $workspace;
        });
    }
}
