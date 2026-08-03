<?php

namespace App\Models;

use App\Enums\ModelStatus;
use App\Traits\HasModelStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Support\Str;

/**
 * Globálna vlastnosť katalógu — Rozmer, Materiál, Uchytenie, Potlač.
 *
 * Pozor: netýka sa Eloquent accessorov (Illuminate\...\Casts\Attribute).
 * Ide o taxonómiu produktov.
 */
class Attribute extends Model
{
    use HasFactory, SoftDeletes, HasModelStatus;

    protected $guarded = [];

    protected $casts = [
        'status'        => ModelStatus::class,
        'is_variant'    => 'boolean',
        'is_filterable' => 'boolean',
        'is_public'     => 'boolean',
    ];

    public function setNameAttribute($value): void
    {
        $this->attributes['name'] = $value;

        if (empty($this->attributes['code'])) {
            $this->attributes['code'] = Str::slug($value, '_');
        }
    }

    public function setCodeAttribute($value): void
    {
        $this->attributes['code'] = Str::slug((string) $value, '_');
    }

    public function values()
    {
        return $this->hasMany(AttributeValue::class)->orderBy('sort_order')->orderBy('value');
    }

    public function products()
    {
        return $this->belongsToMany(Product::class)->withPivot('sort_order');
    }

    public function scopeFilterable($query)
    {
        return $query->where('is_filterable', true);
    }

    public function scopeVariantDefining($query)
    {
        return $query->where('is_variant', true);
    }
}
