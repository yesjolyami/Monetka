<?php

namespace App\Policies;

use App\Models\Account;
use App\Models\User;
use App\Policies\Concerns\AuthorizesWorkspaceMembers;

class AccountPolicy
{
    use AuthorizesWorkspaceMembers;

    public function view(User $user, Account $account): bool
    {
        return $this->allows($user, $account);
    }

    public function update(User $user, Account $account): bool
    {
        return $this->allows($user, $account);
    }

    public function delete(User $user, Account $account): bool
    {
        return $this->allows($user, $account);
    }
}
