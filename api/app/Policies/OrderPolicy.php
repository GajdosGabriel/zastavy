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
        return $user->can('orders.viewAny') || $this->isPortalUser($user);
    }

    public function view(User $user, Order $order): bool
    {
        return ($user->can('orders.view') || $this->isPortalUser($user))
            && $this->ownsOrder($user, $order);
    }

    public function create(User $user): bool
    {
        return $user->can('orders.create') || $this->isPortalUser($user);
    }

    public function update(User $user, Order $order): bool
    {
        return ($user->can('orders.update') || $this->isPortalUser($user))
            && $this->ownsOrder($user, $order);
    }

    public function storno(User $user, Order $order): bool
    {
        return ($user->can('orders.storno') || $this->isPortalUser($user))
            && $this->ownsOrder($user, $order)
            && ! $order->isFinished();
    }

    public function delete(User $user, Order $order): Response
    {
        if (! $user->can('orders.delete')) {
            return Response::deny('Nemáte oprávnenie zmazať objednávku.');
        }

        if ($order->isArchived()) {
            return Response::deny('Archivovanu objednavku nie je mozne zmazat.');
        }

        return $this->ownsOrder($user, $order) && $order->getStockExpeditionAttribute() == 0
            ? Response::allow()
            : Response::deny('Objednavku s expediciou nie je mozne zmazat.');
    }

    public function restore(User $user, Order $order): bool
    {
        return $user->can('orders.update') && $this->ownsOrder($user, $order);
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

    private function isPortalUser(User $user): bool
    {
        return $user->customer_id !== null;
    }
}
