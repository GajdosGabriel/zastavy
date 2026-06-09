<?php

namespace App\Policies;

use App\Models\Stock;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class StockPolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user): bool
    {
        return $user->can('stocks.viewAny');
    }

    public function view(User $user, Stock $stock): bool
    {
        return $user->can('stocks.viewAny');
    }

    public function create(User $user): bool
    {
        return $user->can('stocks.create');
    }

    public function update(User $user, Stock $stock): bool
    {
        return $user->can('stocks.update');
    }

    public function delete(User $user, Stock $stock): bool
    {
        return $user->can('stocks.delete') && ! $stock->isArchived();
    }

    public function restore(User $user, Stock $stock): bool
    {
        return $user->can('stocks.update');
    }

    public function archive(User $user, Stock $stock): bool
    {
        return false;
    }

    public function forceDelete(User $user, Stock $stock): bool
    {
        return false;
    }
}
