<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Coupon extends Model
{
    use SoftDeletes;

    protected $guarded = [];

    protected $casts = [
        'valid_from' => 'date',
        'valid_to'   => 'date',
    ];

    public function isValid(float $cartTotal): bool
    {
        if (! $this->active) {
            return false;
        }

        if ($this->valid_from && Carbon::today()->lt($this->valid_from)) {
            return false;
        }

        if ($this->valid_to && Carbon::today()->gt($this->valid_to)) {
            return false;
        }

        if ($this->usage_limit !== null && $this->used_count >= $this->usage_limit) {
            return false;
        }

        if ($this->min_order_price !== null && $cartTotal < (float) $this->min_order_price) {
            return false;
        }

        return true;
    }

    public function calculateDiscount(float $cartTotal): float
    {
        if ($this->type === 'percent') {
            return round($cartTotal * ((float) $this->value / 100), 2);
        }

        return min((float) $this->value, $cartTotal);
    }
}
