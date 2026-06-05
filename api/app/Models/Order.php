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

    protected $appends = ['productOrderSum'];

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
        return $this->shippingRequiredQuantity() === $this->stockExpedition;
    }

    public function shippintPercentageCalculator()
    {
        if ($this->shippingRequiredQuantity() == 0) {
            return "Prázdna objednávka";
        }

        return $this->shippingPercentage() . '%';
    }

    public function shippingRequiredQuantity()
    {
        return max(0, $this->productOrderSum - $this->productStornoSum());
    }

    public function shippingRemainingQuantity()
    {
        return max(0, $this->shippingRequiredQuantity() - $this->stockExpedition);
    }

    public function shippingPercentage()
    {
        if ($this->shippingRequiredQuantity() == 0) {
            return 0;
        }

        return round(min(100, ($this->stockExpedition / $this->shippingRequiredQuantity()) * 100), 1);
    }

    public function shippingStatusLabel()
    {
        if ($this->isStorned()) {
            return 'Stornovaná';
        }

        if ($this->shippingRequiredQuantity() == 0) {
            return 'Prázdna';
        }

        if ($this->isFinished()) {
            return 'Vybavená';
        }

        if ($this->stockExpedition > 0) {
            return 'Čiastočne vybavená';
        }

        return 'Nevybavená';
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
