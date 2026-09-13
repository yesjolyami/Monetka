<?php

namespace App\Policies;

use App\Models\Transaction;
use App\Models\User;
use App\Policies\Concerns\AuthorizesWorkspaceMembers;

class TransactionPolicy
{
    use AuthorizesWorkspaceMembers;

    public function view(User $user, Transaction $transaction): bool
    {
        return $this->allows($user, $transaction);
    }

    public function update(User $user, Transaction $transaction): bool
    {
        return $this->allows($user, $transaction);
    }

    public function delete(User $user, Transaction $transaction): bool
    {
        return $this->allows($user, $transaction);
    }
}
