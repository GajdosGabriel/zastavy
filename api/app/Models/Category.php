<?php

namespace App\Models;

use App\Models\Product;
use Illuminate\Support\Str;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Category extends Model
{
    use HasFactory, SoftDeletes;

    protected $guarded = [];

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
