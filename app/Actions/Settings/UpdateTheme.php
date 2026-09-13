<?php

namespace App\Actions\Settings;

use App\Enums\Appearance;
use App\Models\User;

class UpdateTheme
{
    public function execute(User $user, Appearance $theme): void
    {
        $user->forceFill(['theme' => $theme])->save();
    }
}
