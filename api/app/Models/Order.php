<?php

namespace App\Models;

use App\Models\Mark;
use App\Models\Stock;
use App\Models\Shipping;
use App\Models\OrderProduct;
use App\Traits\HasNotices;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Order extends Model
{
    use HasFactory, SoftDeletes, Notifiable, HasNotices;

    protected $guarded = [];
    // protected $fillable = ['productStornoSum', 'serial_number', 'notice'];

    protected $appends = ['productOrderSum'];

    // protected $casts = [
    //     'star' => 'boolean',
    // ];


    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function shippings()
    {
        return $this->hasMany(Shipping::class);
    }

    public function orderProducts()
    {
        return $this->hasMany(OrderProduct::class);
    }

    public function stocks()
    {
        return $this->hasMany(Stock::class);
    }

    public function mark()
    {
        return $this->morphOne(Mark::class, 'fileable');
    }

    public function priceSum()
    {
        return $this->orderProducts->sum('total');
    }

    public function getProductOrderSumAttribute()
    {
        return $this->orderProducts->sum('quantity');
    }

    public function isStorned()
    {
        return $this->productStornoSum() == $this->orderProducts->sum('quantity');
    }

    public function productStornoSum()
    {
        return $this->orderProducts->sum('storno');
    }

    public function isFinished()
    {
        return  $this->productOrderSum - $this->productStornoSum() === $this->stockExpedition;
    }

    public function shippintPercentageCalculator()
    {
        // Nulou nemožno deliť, preto iba nie prázbne objednávky.
        if ($this->productOrderSum - $this->productStornoSum() == 0) {
            return "Prázdna objednávka";
        }
        return (100 / $this->productOrderSum - $this->productStornoSum()) * $this->stockExpedition . '%';
    }

    public function getStockExpeditionAttribute()
    {
        return $this->stocks()->get()->sum('quantity');
    }

    public function scopeFilter($query, $filters)
    {
        return $filters->apply($query);
    }
}
