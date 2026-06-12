<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class OrderReturnItem extends Model
{
    use HasFactory;

    protected $guarded = [];

    public function orderReturn()
    {
        return $this->belongsTo(OrderReturn::class);
    }

    public function orderProduct()
    {
        return $this->belongsTo(OrderProduct::class);
    }
}
