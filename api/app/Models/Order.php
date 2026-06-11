<?php

namespace App\Models;

use App\Enums\ModelStatus;
use App\Traits\HasModelStatus;
use App\Traits\HasNotices;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Str;

class Order extends Model
{
    use HasFactory, HasModelStatus, HasNotices, Notifiable, SoftDeletes;

    protected $guarded = [];

    protected $appends = ['productOrderSum'];

    protected $casts = [
        'status' => ModelStatus::class,
    ];

    protected static function booted(): void
    {
        static::creating(function (Order $order) {
            if (! $order->uuid) {
                $order->uuid = (string) Str::uuid();
            }
        });
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function shippingMethod()
    {
        return $this->belongsTo(ShippingMethod::class);
    }

    public function paymentMethod()
    {
        return $this->belongsTo(PaymentMethod::class);
    }

    public function coupon()
    {
        return $this->belongsTo(Coupon::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
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
        if ($this->status === ModelStatus::Cancelled) {
            return true;
        }

        return $this->productStornoSum() == $this->orderProducts->sum('quantity');
    }

    public function productStornoSum()
    {
        return $this->orderProducts->sum('storno');
    }

    public function isFinished()
    {
        if ($this->isStorned()) {
            return false;
        }

        return $this->shippingRequiredQuantity() === $this->stockExpedition;
    }

    public function shippintPercentageCalculator()
    {
        if ($this->shippingRequiredQuantity() == 0) {
            return 'Prázdna objednávka';
        }

        return $this->shippingPercentage().'%';
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
