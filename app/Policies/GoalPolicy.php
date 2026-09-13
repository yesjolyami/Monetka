<?php

namespace App\Policies;

use App\Models\Goal;
use App\Models\User;
use App\Policies\Concerns\AuthorizesWorkspaceMembers;

class GoalPolicy
{
    use AuthorizesWorkspaceMembers;

    public function view(User $user, Goal $goal): bool
    {
        return $this->allows($user, $goal);
    }

    public function update(User $user, Goal $goal): bool
    {
        return $this->allows($user, $goal);
    }

    public function delete(User $user, Goal $goal): bool
    {
        return $this->allows($user, $goal);
    }
}
