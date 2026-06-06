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
    protected $appends = ['product'];

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

    public function stocks()
    {
        return $this->hasMany(Stock::class);
    }

    public function getStockSumAttribute()
    {
      return $this->stocks()->where('order_product_id', '=', $this->id)->get()->sum('quantity');
    }
}
