<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ShippingMethod extends Model
{
    use SoftDeletes;

    protected $guarded = [];

    public function resolvePrice(float $cartTotal): float
    {
        if ($this->free_from_price !== null && $cartTotal >= (float) $this->free_from_price) {
            return 0.0;
        }

        return (float) $this->price;
    }
}
