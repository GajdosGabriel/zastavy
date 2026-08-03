<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Support\Str;

class AttributeValue extends Model
{
    use HasFactory;

    protected $guarded = [];

    public function setValueAttribute($value): void
    {
        $this->attributes['value'] = $value;
        $this->attributes['slug']  = Str::slug($value, '-');

        if (empty($this->attributes['code'])) {
            $this->attributes['code'] = Str::slug($value, '-');
        }
    }

    public function attribute()
    {
        return $this->belongsTo(Attribute::class);
    }

    public function variants()
    {
        return $this->belongsToMany(ProductVariant::class);
    }

    public function products()
    {
        return $this->belongsToMany(Product::class)->withPivot('is_variant_option');
    }

    /**
     * Segment pre SEO URL, napr. rozmer-100x150-cm.
     */
    public function getFacetSlugAttribute(): string
    {
        return $this->attribute?->code
            ? Str::slug($this->attribute->code, '-') . '-' . $this->slug
            : $this->slug;
    }
}
