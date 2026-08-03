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

    public function variant()
    {
        return $this->belongsTo(ProductVariant::class, 'product_variant_id');
    }

    public function getProductAttribute(): ?Product
    {
        if ($this->product_id) {
            return $this->productDirect;
        }
        return $this->orderProduct?->product;
    }

    /**
     * Skladová položka, ktorej sa pohyb týka — príjem ju má priamo,
     * výdaj cez položku objednávky.
     */
    public function getProductVariantAttribute(): ?ProductVariant
    {
        if ($this->product_variant_id) {
            return $this->variant;
        }

        return $this->orderProduct?->variant;
    }

    public function scopeFilter($query, $filters)
    {
        return $filters->apply($query);
    }
}
