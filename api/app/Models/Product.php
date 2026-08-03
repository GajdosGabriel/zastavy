<?php

namespace App\Models;


use App\Enums\ModelStatus;
use App\Models\Category;
use App\Traits\HasModelStatus;
use App\Traits\HasNotices;
use Illuminate\Support\Str;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Product extends Model
{
    use HasFactory, SoftDeletes, HasNotices, HasModelStatus;

    protected $guarded = [];

    protected $casts = [
        'status' => ModelStatus::class,
    ];

    public function setNameAttribute($value)
    {
        $this->attributes['name'] =  $value;
        $this->attributes['slug'] =  Str::slug($value, '-');
    }

    public function setCodeAttribute($value)
    {
        $this->attributes['code'] = Str::upper(trim((string) $value));
    }

    public function variants()
    {
        return $this->hasMany(ProductVariant::class)->orderBy('sort_order')->orderBy('id');
    }

    public function defaultVariant()
    {
        return $this->hasOne(ProductVariant::class)->where('is_default', true);
    }

    /**
     * Vlastnosti, ktoré produkt používa (poradie v editore aj na karte).
     */
    public function attributesTaxonomy()
    {
        return $this->belongsToMany(Attribute::class)->withPivot('sort_order')->orderBy('attribute_product.sort_order');
    }

    /**
     * Fasetový index — hodnoty odvodené z variantov plus ručne priradené.
     */
    public function attributeValues()
    {
        return $this->belongsToMany(AttributeValue::class)->withPivot('is_variant_option');
    }

    public function orderProducts()
    {
        return $this->hasMany(OrderProduct::class);
    }

    public function images()
    {
        return $this->morphMany(Image::class, 'fileable')->orderBy('sort_order');
    }

    public function categories()
    {
        return $this->belongsToMany(Category::class);
    }

    public function publishedVariants()
    {
        return $this->variants()->where('published', true);
    }

    /**
     * Predajné varianty, z ktorých sa počíta cena a dostupnosť na karte.
     */
    protected function sellable(): \Illuminate\Support\Collection
    {
        return $this->variants->where('published', true)->values();
    }

    public function getPriceFromAttribute(): ?float
    {
        $prices = $this->sellable()->map->active_price->filter(fn ($p) => $p !== null);

        return $prices->isEmpty() ? null : (float) $prices->min();
    }

    public function getPriceToAttribute(): ?float
    {
        $prices = $this->sellable()->map->active_price->filter(fn ($p) => $p !== null);

        return $prices->isEmpty() ? null : (float) $prices->max();
    }

    /**
     * null = sklad sa na žiadnom variante nesleduje.
     */
    public function getTotalQuantityAttribute(): ?int
    {
        $tracked = $this->sellable()->filter(fn ($v) => $v->quantity !== null);

        return $tracked->isEmpty() ? null : (int) $tracked->sum('quantity');
    }

    public function getIsInStockAttribute(): bool
    {
        return $this->sellable()->contains(fn ($v) => $v->is_in_stock);
    }

    public function getThumbAttribute()
    {
        $image = $this->images->first();
        if ($image) {
            $path = preg_replace('#^public/#', '', $image->path);

            return Storage::disk('public')->url($path);
        }

        // Lokálny placeholder — bez závislosti na externej službe (výkon, súkromie, dostupnosť).
        return asset('images/product-placeholder.svg');
    }

    public function scopeFilter($query, $filters)
    {
        return $filters->apply($query);
    }

    /**
     * Ak sa tovar nachádza v objednávky.
     *
     * @return int
     */

    public function getOrderProductsCount()
    {
        return $this->orderProducts()->count();
    }
}
