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
        return $user->can('orders.viewAny') || $this->portalCan($user, 'orders.viewAny');
    }

    public function view(User $user, Order $order): bool
    {
        return ($user->can('orders.view') || $this->portalCan($user, 'orders.view'))
            && $this->ownsOrder($user, $order);
    }

    public function create(User $user): bool
    {
        return $user->can('orders.create') || $this->portalCan($user, 'orders.create');
    }

    public function update(User $user, Order $order): bool
    {
        return ($user->can('orders.update') || $this->portalCan($user, 'orders.update'))
            && $this->ownsOrder($user, $order);
    }

    public function storno(User $user, Order $order): bool
    {
        return ($user->can('orders.storno') || $this->portalCan($user, 'orders.storno'))
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

    public function archive(): bool
    {
        return false;
    }

    public function forceDelete(): bool
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

    /**
     * Portal user s priamo pridelenými permissions ich rešpektuje.
     * Bez priamo pridelených permissions dostane základný prístup k objednávkam.
     */
    private function portalCan(User $user, string $permission): bool
    {
        if (! $this->isPortalUser($user)) {
            return false;
        }

        if ($user->getDirectPermissions()->isNotEmpty()) {
            return $user->hasDirectPermission($permission);
        }

        return in_array($permission, [
            'orders.viewAny', 'orders.view', 'orders.create', 'orders.update', 'orders.storno',
        ]);
    }
}
