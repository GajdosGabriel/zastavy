<?php

namespace App\Models;


use App\Enums\ModelStatus;
use App\Models\Stock;
use App\Models\Category;
use App\Traits\HasModelStatus;
use App\Traits\HasNotices;
use Illuminate\Support\Str;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Product extends Model
{
    use HasFactory, SoftDeletes, HasNotices, HasModelStatus;

    protected $guarded = [];
    // protected $appends = ['activePrice'];

    protected $filleable = [
        'code',
        'name',
        'slug',
        'description',
        'quantity',
        'weight',
        'price',
        'sale_price',
        'discount',
        'vat',
        'featured',
        'published',
        'unit_value',
        'min_order',
    ];

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

    // protected function sale_price(): Attribute
    // {
    //     return Attribute::make(
    //         set: fn ($value) =>  ($this->discount / 100) * $value,
    //     );
    // }

    public function orderProducts()
    {
        return $this->hasMany(OrderProduct::class);
    }

    public function images()
    {
        return $this->morphMany(Image::class, 'fileable');
    }

    public function categories()
    {
        return $this->belongsToMany(Category::class);
    }

    public function getActivePriceAttribute()
    {
        if ($this->sale_price > 0) {
            return $this->sale_price;
        }
        return $this->price;
    }


    public function getThumbAttribute()
    {
        $image = $this->images->first();
        if ($image) {
            $path = preg_replace('#^public/#', '', $image->path);

            return Storage::disk('public')->url($path);
        }

        return url('https://images.unsplash.com/photo-1494790108377-be9c29b29330?ixlib=rb-1.2.1&ixid=eyJhcHBfaWQiOjEyMDd9&auto=format&fit=facearea&facepad=4&w=256&h=256&q=60');
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
