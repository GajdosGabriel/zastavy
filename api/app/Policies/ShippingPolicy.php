<?php

namespace App\Policies;

use App\Models\Shipping;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class ShippingPolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user): bool
    {
        return $user->can('shippings.manage');
    }

    public function view(User $user, Shipping $shipping): bool
    {
        return $user->can('shippings.manage');
    }

    public function create(User $user): bool
    {
        return $user->can('shippings.manage');
    }

    public function update(User $user, Shipping $shipping): bool
    {
        return $user->can('shippings.manage');
    }

    public function delete(User $user, Shipping $shipping): bool
    {
        return $user->can('shippings.manage') && ! $shipping->isArchived();
    }

    public function restore(User $user, Shipping $shipping): bool
    {
        return $user->can('shippings.manage');
    }

    public function archive(User $user, Shipping $shipping): bool
    {
        return false;
    }

    public function forceDelete(User $user, Shipping $shipping): bool
    {
        return false;
    }
}
