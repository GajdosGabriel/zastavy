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
        return $this->ownsOrder($user, $order);
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, Order $order): bool
    {
        return $this->ownsOrder($user, $order);
    }

    public function storno(User $user, Order $order): bool
    {
        return $this->ownsOrder($user, $order) && ! $order->isFinished();
    }

    public function delete(User $user, Order $order): Response
    {
        return $this->ownsOrder($user, $order) && $order->getStockExpeditionAttribute() == 0
            ? Response::allow()
            : Response::deny('Objednavku s expediciou nie je mozne zmazat.');
    }

    public function restore(User $user, Order $order): bool
    {
        return $this->ownsOrder($user, $order);
    }

    public function archive(User $user, Order $order): bool
    {
        return false;
    }

    public function forceDelete(User $user, Order $order): bool
    {
        return false;
    }

    private function ownsOrder(User $user, Order $order): bool
    {
        if ($user->hasRole('super-admin')) {
            return true;
        }

        return $order->user_id === $user->id
            || ($user->customer_id !== null && $order->customer_id === $user->customer_id);
    }
}
