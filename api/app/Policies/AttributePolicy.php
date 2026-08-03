<?php

namespace App\Policies;

use App\Models\Attribute;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class AttributePolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user): bool
    {
        return $user->can('attributes.viewAny');
    }

    public function view(User $user, Attribute $attribute): bool
    {
        return $user->can('attributes.viewAny');
    }

    public function create(User $user): bool
    {
        return $user->can('attributes.manage');
    }

    public function update(User $user, Attribute $attribute): bool
    {
        return $user->can('attributes.manage');
    }

    /**
     * Vlastnosť použitú na variante nemožno zmazať — rozbila by kombinácie.
     */
    public function delete(User $user, Attribute $attribute): bool
    {
        return $user->can('attributes.manage')
            && ! $attribute->values()
                ->whereHas('variants')
                ->exists();
    }

    public function restore(User $user, Attribute $attribute): bool
    {
        return $user->can('attributes.manage');
    }

    public function forceDelete(User $user, Attribute $attribute): bool
    {
        return false;
    }
}
