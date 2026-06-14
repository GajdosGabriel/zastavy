<?php

namespace App\Policies;

use App\Models\OrderProduct;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class OrderProductPolicy
{
    public function delete(User $user, OrderProduct $orderProduct): Response
    {
        $order = $orderProduct->order;

        if (! $user->can('orders.update')) {
            return Response::deny('Nemáte oprávnenie mazať položky objednávky.');
        }

        if (! ($order->user_id === $user->id
            || ($user->customer_id !== null && $order->customer_id === $user->customer_id)
            || $user->hasRole('super-admin'))) {
            return Response::deny('Nemáte prístup k tejto objednávke.');
        }

        if ($orderProduct->stockSum > 0) {
            return Response::deny('Expedovanú položku nie je možné zmazať.');
        }

        return Response::allow();
    }
}
