<?php

namespace App\Policies;

use App\Models\Category;
use App\Models\User;
use App\Policies\Concerns\AuthorizesWorkspaceMembers;

class CategoryPolicy
{
    use AuthorizesWorkspaceMembers;

    public function view(User $user, Category $category): bool
    {
        return $this->allows($user, $category);
    }

    public function update(User $user, Category $category): bool
    {
        return $this->allows($user, $category);
    }

    public function delete(User $user, Category $category): bool
    {
        return $this->allows($user, $category);
    }
}
