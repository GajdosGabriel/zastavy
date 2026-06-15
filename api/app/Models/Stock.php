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

    public function productDirect()
    {
        return $this->belongsTo(Product::class, 'product_id');
    }

    public function getProductAttribute(): ?Product
    {
        if ($this->product_id) {
            return $this->productDirect;
        }
        return $this->orderProduct?->product;
    }

    public function scopeFilter($query, $filters)
    {
        return $filters->apply($query);
    }
}
