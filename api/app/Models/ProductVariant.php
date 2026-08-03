<?php

namespace App\Models;

use App\Enums\ModelStatus;
use App\Traits\HasModelStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Skladová položka produktu. Nesie cenu, sklad, váhu a EAN.
 */
class ProductVariant extends Model
{
    use HasFactory, SoftDeletes, HasModelStatus;

    protected $guarded = [];

    protected $casts = [
        'status'     => ModelStatus::class,
        'is_default' => 'boolean',
        'published'  => 'boolean',
        'price'      => 'decimal:2',
        'sale_price' => 'decimal:2',
        'discount'   => 'decimal:2',
        'weight'     => 'decimal:2',
    ];

    public function setCodeAttribute($value): void
    {
        $this->attributes['code'] = Str::upper(trim((string) $value));
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function attributeValues()
    {
        return $this->belongsToMany(AttributeValue::class);
    }

    public function orderProducts()
    {
        return $this->hasMany(OrderProduct::class);
    }

    public function stocks()
    {
        return $this->hasMany(Stock::class);
    }

    public function image()
    {
        return $this->belongsTo(Image::class);
    }

    public function scopePublished($query)
    {
        return $query->where('published', true);
    }

    public function getActivePriceAttribute()
    {
        if ($this->sale_price > 0) {
            return $this->sale_price;
        }

        return $this->price;
    }

    public function getIsInStockAttribute(): bool
    {
        // Tovar na zákazku je dostupný bez ohľadu na sklad. Reláciu čítame len
        // keď je načítaná — inak by si ju každý variant v zozname dotiahol
        // vlastným dotazom (ProductResource ju nastavuje dopredu).
        if ($this->relationLoaded('product') && $this->product?->made_to_order) {
            return true;
        }

        // quantity === null znamená "sklad sa nesleduje", nie "vypredané".
        return $this->quantity === null || (int) $this->quantity > 0;
    }

    /**
     * Popis kombinácie ("100 × 150 cm / Polyester"). Držíme ho aj v stĺpci name,
     * aby sa dal zobraziť bez načítania pivotu.
     */
    public function buildLabel(): ?string
    {
        $values = $this->relationLoaded('attributeValues')
            ? $this->attributeValues
            : $this->attributeValues()->with('attribute')->get();

        if ($values->isEmpty()) {
            return null;
        }

        return $values
            ->sortBy(fn (AttributeValue $value) => $value->attribute?->sort_order ?? 0)
            ->pluck('value')
            ->implode(' / ');
    }

    public function refreshLabel(): void
    {
        $this->forceFill(['name' => $this->buildLabel()])->save();
    }

    public function getThumbAttribute(): string
    {
        $image = $this->image ?? $this->product?->images->first();

        if ($image) {
            return Storage::disk('public')->url(preg_replace('#^public/#', '', $image->path));
        }

        return asset('images/product-placeholder.svg');
    }

    public function getOrderProductsCount(): int
    {
        return $this->orderProducts()->count();
    }

    public function scopeFilter($query, $filters)
    {
        return $filters->apply($query);
    }
}
