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
        'product_snapshot' => 'array',
        'status' => ModelStatus::class,
    ];

    protected static function booted(): void
    {
        static::creating(function (OrderProduct $item) {
            $item->product_snapshot ??= $item->productSnapshot();
        });
    }

    public function productSnapshot(): array
    {
        return $this->product_snapshot ?? [
            'name' => $this->product?->name ?? '—',
            'code' => $this->product?->code,
            'unit_value' => $this->product?->unit_value ?? 'ks',
            'vat' => $this->product?->vat,
            'variant_name' => $this->variant_label ?? $this->variant?->name,
            'variant_code' => $this->variant?->code,
        ];
    }

    public function getProductDetailsAttribute(): object
    {
        return (object) $this->productSnapshot();
    }

    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    public function product()
    {
        return $this->belongsTo(Product::class)->withTrashed();
    }

    public function variant()
    {
        return $this->belongsTo(ProductVariant::class, 'product_variant_id')->withTrashed();
    }

    /**
     * Popis variantu na doklade. Preferuje snapshot z času objednávky —
     * variant sa mohol medzitým premenovať alebo zmazať.
     */
    public function getVariantNameAttribute(): ?string
    {
        return $this->product_snapshot !== null ? ($this->product_snapshot['variant_name'] ?? null) : ($this->variant_label ?: $this->variant?->name);
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
