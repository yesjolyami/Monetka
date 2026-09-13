<?php

namespace App\Actions\Workspaces;

use App\Models\User;
use App\Models\Workspace;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class DeleteWorkspace
{
    public function execute(User $actor, Workspace $workspace): void
    {
        Gate::forUser($actor)->authorize('delete', $workspace);

        DB::transaction(function () use ($workspace): void {
            User::query()
                ->where('current_workspace_id', $workspace->id)
                ->update(['current_workspace_id' => null]);

            $workspace->transactions()->delete();
            $workspace->recurrences()->delete();
            $workspace->categoryLimits()->delete();
            $workspace->goals()->delete();
            $workspace->debts()->delete();
            $workspace->accounts()->delete();
            $workspace->banks()->delete();
            $workspace->categories()->delete();
            $workspace->invitations()->delete();

            $workspace->delete();
        });
    }
}
