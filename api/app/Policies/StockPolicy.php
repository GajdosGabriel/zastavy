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
        return true;
    }

    public function view(User $user, Stock $stock): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, Stock $stock): bool
    {
        return true;
    }

    public function delete(User $user, Stock $stock): bool
    {
        return true;
    }

    public function restore(User $user, Stock $stock): bool
    {
        return true;
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
