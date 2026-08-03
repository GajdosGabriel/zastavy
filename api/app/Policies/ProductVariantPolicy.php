<?php

namespace App\Policies;

use App\Models\ProductVariant;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

/**
 * Variant je súčasť produktu — dedí oprávnenia products.*.
 */
class ProductVariantPolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user): bool
    {
        return $user->can('products.viewAny');
    }

    public function view(User $user, ProductVariant $variant): bool
    {
        return $user->can('products.view');
    }

    public function create(User $user): bool
    {
        return $user->can('products.create');
    }

    public function update(User $user, ProductVariant $variant): bool
    {
        return $user->can('products.update');
    }

    /**
     * Variant v objednávke sa nemaže — doklad by stratil položku.
     */
    public function delete(User $user, ProductVariant $variant): bool
    {
        return $user->can('products.delete')
            && ! $variant->isArchived()
            && $variant->getOrderProductsCount() === 0;
    }

    public function restore(User $user, ProductVariant $variant): bool
    {
        return $user->can('products.update');
    }

    public function forceDelete(User $user, ProductVariant $variant): bool
    {
        return false;
    }
}
