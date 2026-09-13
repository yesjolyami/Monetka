<?php

namespace App\Actions\Invitations;

use App\Enums\WorkspaceRole;
use App\Models\Invitation;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AcceptInvitation
{
    public function execute(User $user, Invitation $invitation): void
    {
        if (Str::lower($user->email) !== Str::lower($invitation->email)) {
            throw ValidationException::withMessages([
                'invitation' => 'Приглашение отправлено на другой адрес.',
            ]);
        }

        if ($invitation->accepted_at !== null || $invitation->expires_at->lte(now())) {
            throw ValidationException::withMessages([
                'invitation' => 'Приглашение недействительно или истекло.',
            ]);
        }

        DB::transaction(function () use ($user, $invitation) {
            $workspace = Workspace::query()->findOrFail($invitation->workspace_id);

            $workspace->memberships()->create([
                'user_id' => $user->id,
                'role' => WorkspaceRole::Member,
            ]);

            $invitation->forceFill(['accepted_at' => now()])->save();

            if ($user->current_workspace_id === null) {
                $user->forceFill(['current_workspace_id' => $workspace->id])->save();
            }
        });
    }
}
