<?php

namespace App\Policies;

use App\Models\CategoryLimit;
use App\Models\User;
use App\Policies\Concerns\AuthorizesWorkspaceMembers;

class CategoryLimitPolicy
{
    use AuthorizesWorkspaceMembers;

    public function view(User $user, CategoryLimit $categoryLimit): bool
    {
        return $this->allows($user, $categoryLimit);
    }

    public function update(User $user, CategoryLimit $categoryLimit): bool
    {
        return $this->allows($user, $categoryLimit);
    }

    public function delete(User $user, CategoryLimit $categoryLimit): bool
    {
        return $this->allows($user, $categoryLimit);
    }
}
