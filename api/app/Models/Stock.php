<?php

namespace App\Models;

use App\Enums\ModelStatus;
use App\Models\Product;
use App\Models\Shipping;
use App\Models\OrderProduct;
use App\Traits\HasModelStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Stock extends Model
{
    use HasFactory, SoftDeletes, HasModelStatus;

    protected $guarded = [];
    // protected $with = ['orderProduct'];

    protected $casts = [
        'status' => ModelStatus::class,
    ];

    public function shipping()
    {
        return $this->belongsTo(Shipping::class);
    }

    public function orderReturn()
    {
        return $this->belongsTo(OrderReturn::class);
    }


    public function orderProduct()
    {
        return $this->belongsTo(OrderProduct::class);
    }

    public function product()
    {
        return $this->orderProduct->belongsTo(Product::class);
    }

    public function scopeFilter($query, $filters)
    {
        return $filters->apply($query);
    }
}
