<?php

namespace App\Models;

use App\Models\Order;
use App\Models\Stock;
use App\Traits\HasNotices;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Shipping extends Model
{
    use HasFactory, HasNotices;
    protected $guarded = [];

    public function stocks()
    {
        return $this->hasMany(Stock::class);
    }

    public function order()
    {
        return $this->belongsTo(Order::class);
    }

}
