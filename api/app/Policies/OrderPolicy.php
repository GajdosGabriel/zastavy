<?php

namespace App\Policies;

use App\Models\Order;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Auth\Access\Response;

class OrderPolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Order $order): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, Order $order): bool
    {
        return true;
    }

    public function storno(User $user, Order $order): bool
    {
        return !$order->isFinished();
    }

    public function delete(User $user, Order $order): Response
    {
        return $order->getStockExpeditionAttribute() == 0
            ? Response::allow()
            : Response::deny('Objednávku s expedíciou nie je možné zmazať.');
    }

    public function restore(User $user, Order $order): bool
    {
        return true;
    }

    public function archive(User $user, Order $order): bool
    {
        return false;
    }

    public function forceDelete(User $user, Order $order): bool
    {
        return false;
    }
}
