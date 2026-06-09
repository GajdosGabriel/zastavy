<?php

namespace App\Policies;

use App\Models\Category;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class CategoryPolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user): bool
    {
        return $user->can('categories.manage');
    }

    public function view(User $user, Category $category): bool
    {
        return $user->can('categories.manage');
    }

    public function create(User $user): bool
    {
        return $user->can('categories.manage');
    }

    public function update(User $user, Category $category): bool
    {
        return $user->can('categories.manage');
    }

    public function delete(User $user, Category $category): bool
    {
        return $user->can('categories.manage') && ! $category->isArchived();
    }

    public function restore(User $user, Category $category): bool
    {
        return $user->can('categories.manage');
    }

    public function archive(User $user, Category $category): bool
    {
        return false;
    }

    public function forceDelete(User $user, Category $category): bool
    {
        return false;
    }
}
