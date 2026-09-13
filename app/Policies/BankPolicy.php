<?php

namespace App\Policies;

use App\Models\Bank;
use App\Models\User;
use App\Policies\Concerns\AuthorizesWorkspaceMembers;

class BankPolicy
{
    use AuthorizesWorkspaceMembers;

    public function view(User $user, Bank $bank): bool
    {
        return $this->allows($user, $bank);
    }

    public function update(User $user, Bank $bank): bool
    {
        return $this->allows($user, $bank);
    }

    public function delete(User $user, Bank $bank): bool
    {
        return $this->allows($user, $bank);
    }
}
