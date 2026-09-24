<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OrderProduction extends Model
{
    protected $guarded = [];

    protected $hidden = ['token_hash'];

    protected $casts = ['production_due_at' => 'date:Y-m-d', 'delivery_due_at' => 'date:Y-m-d', 'token_expires_at' => 'datetime'];

    public function order()
    {
        return $this->belongsTo(Order::class);
    }
}
