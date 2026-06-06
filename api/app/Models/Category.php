<?php

namespace App\Models;

use App\Enums\ModelStatus;
use App\Models\Product;
use App\Traits\HasModelStatus;
use Illuminate\Support\Str;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Category extends Model
{
    use HasFactory, SoftDeletes, HasModelStatus;

    protected $guarded = [];

    protected $casts = [
        'status' => ModelStatus::class,
    ];

    public function products()
    {
        return $this->belongsToMany(Product::class);
    }

    public function setNameAttribute($value)
    {
        $this->attributes['name'] =  $value;
        $this->attributes['slug'] =  Str::slug($value, '-');
    }
}
