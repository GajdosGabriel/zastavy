<?php

namespace App\Models;

use App\Enums\ModelStatus;
use App\Models\Stock;
use App\Traits\HasModelStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class OrderProduct extends Model
{
    use HasFactory, SoftDeletes, HasModelStatus;

    protected $guarded = [];

    protected $casts = [
        'status' => ModelStatus::class,
    ];

    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function variant()
    {
        return $this->belongsTo(ProductVariant::class, 'product_variant_id');
    }

    /**
     * Popis variantu na doklade. Preferuje snapshot z času objednávky —
     * variant sa mohol medzitým premenovať alebo zmazať.
     */
    public function getVariantNameAttribute(): ?string
    {
        return $this->variant_label ?: $this->variant?->name;
    }

    public function stocks()
    {
        return $this->hasMany(Stock::class);
    }

    public function getStockSumAttribute(): int
    {
        return $this->relationLoaded('stocks')
            ? (int) $this->stocks->sum('quantity')
            : (int) $this->stocks()->sum('quantity');
    }
}
