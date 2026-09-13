<?php

namespace App\Policies;

use App\Models\Debt;
use App\Models\User;
use App\Policies\Concerns\AuthorizesWorkspaceMembers;

class DebtPolicy
{
    use AuthorizesWorkspaceMembers;

    public function view(User $user, Debt $debt): bool
    {
        return $this->allows($user, $debt);
    }

    public function update(User $user, Debt $debt): bool
    {
        return $this->allows($user, $debt);
    }

    public function delete(User $user, Debt $debt): bool
    {
        return $this->allows($user, $debt);
    }
}
