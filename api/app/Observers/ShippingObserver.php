<?php

namespace App\Observers;

use App\Enums\ModelStatus;
use App\Models\Shipping;


class ShippingObserver
{
    public function created(Shipping $shipping)
    {
        $shipping->order()->update([
            'status' => ModelStatus::Archived,
        ]);
    }
}
