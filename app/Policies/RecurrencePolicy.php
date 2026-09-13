<?php

namespace App\Policies;

use App\Models\Recurrence;
use App\Models\User;
use App\Policies\Concerns\AuthorizesWorkspaceMembers;

class RecurrencePolicy
{
    use AuthorizesWorkspaceMembers;

    public function view(User $user, Recurrence $recurrence): bool
    {
        return $this->allows($user, $recurrence);
    }

    public function update(User $user, Recurrence $recurrence): bool
    {
        return $this->allows($user, $recurrence);
    }

    public function delete(User $user, Recurrence $recurrence): bool
    {
        return $this->allows($user, $recurrence);
    }
}
