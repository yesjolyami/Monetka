<?php

namespace App\Actions\Invitations;

use App\Models\Invitation;
use App\Models\User;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class DeclineInvitation
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

        $invitation->forceFill(['expires_at' => now()->subSecond()])->save();
    }
}
