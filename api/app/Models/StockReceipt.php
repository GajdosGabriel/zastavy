<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StockReceipt extends Model
{
    protected $guarded = [];

    protected $casts = ['received_at' => 'date', 'cancelled_at' => 'datetime'];

    public function items()
    {
        return $this->hasMany(Stock::class)->withTrashed()->orderBy('id');
    }
}
