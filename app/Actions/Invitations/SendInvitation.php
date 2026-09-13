<?php

namespace App\Actions\Invitations;

use App\Models\Invitation;
use App\Models\User;
use App\Models\Workspace;
use App\Notifications\WorkspaceInvitationNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class SendInvitation
{
    public function execute(User $actor, Workspace $workspace, string $email): Invitation
    {
        Gate::forUser($actor)->authorize('invite', $workspace);

        $normalizedEmail = Str::lower($email);

        $alreadyMember = $workspace->memberships()
            ->whereHas('user', fn ($query) => $query->whereRaw('LOWER(email) = ?', [$normalizedEmail]))
            ->exists();

        if ($alreadyMember) {
            throw ValidationException::withMessages([
                'email' => 'Этот пользователь уже состоит в бюджете.',
            ]);
        }

        $invitation = DB::transaction(function () use ($actor, $workspace, $email, $normalizedEmail) {
            Invitation::query()
                ->where('workspace_id', $workspace->id)
                ->whereRaw('LOWER(email) = ?', [$normalizedEmail])
                ->whereNull('accepted_at')
                ->where('expires_at', '>', now())
                ->update(['expires_at' => now()->subSecond()]);

            return Invitation::query()->create([
                'workspace_id' => $workspace->id,
                'email' => $email,
                'token' => bin2hex(random_bytes(32)),
                'invited_by' => $actor->id,
                'expires_at' => now()->addDays(7),
            ]);
        });

        $invitation->load('workspace');

        Notification::route('mail', $email)
            ->notify(new WorkspaceInvitationNotification($invitation));

        return $invitation;
    }
}
