<?php

namespace App\Observers;

use App\Enums\OrderStatus;
use App\Models\Shipping;


class ShippingObserver
{
    public function created(Shipping $shipping)
    {
        $shipping->order()->update([
            'status' => OrderStatus::Archived,
        ]);
    }
}
