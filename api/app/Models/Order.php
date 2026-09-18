<?php

namespace App\Models;

use App\Enums\OrderStatus;
use App\Support\AddressFormatter;
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
        'billing_snapshot' => 'array',
        'status' => OrderStatus::class,
        'delivery_token_expires_at' => 'datetime',
        'delivery_changed_at' => 'datetime',
    ];

    /** Dokedy platí odkaz „zmeniť adresu doručenia" z potvrdzovacieho e-mailu. */
    public const DELIVERY_TOKEN_DAYS = 60;

    protected static function booted(): void
    {
        static::creating(function (Order $order) {
            $order->billing_snapshot ??= $order->billingSnapshot();
            $order->snapshot_source ??= 'captured';
            $order->shipping_method_name ??= $order->shippingMethod?->name;
            $order->payment_method_name ??= $order->paymentMethod?->name;
            if (! $order->uuid) {
                $order->uuid = (string) Str::uuid();
            }

            if (! $order->delivery_token) {
                $order->delivery_token = Str::random(64);
                $order->delivery_token_expires_at = now()->addDays(self::DELIVERY_TOKEN_DAYS);
            }
        });
    }

    public function billingSnapshot(): array
    {
        if ($this->billing_snapshot !== null) return $this->billing_snapshot;
        $customer = $this->customer;
        return [
            'name' => $this->name ?: $customer?->name,
            'company' => $customer?->company,
            'email' => $this->email ?: $customer?->email,
            'phone' => $this->phone ?: $customer?->phone,
            'street' => $customer?->street,
            'postcode' => $customer?->postcode,
            'city' => $customer?->city,
            'country' => 'SK',
            'ico' => $customer?->ico,
            'dic' => $customer?->dic,
            'ic_dic' => $customer?->ic_dic,
        ];
    }

    public function getShippingLabelAttribute(): ?string
    {
        return $this->shipping_method_name ?? $this->shippingMethod?->name;
    }

    public function getPaymentLabelAttribute(): ?string
    {
        return $this->payment_method_name ?? $this->paymentMethod?->name;
    }

    public function getBillingAttribute(): object
    {
        return (object) $this->billingSnapshot();
    }

    public function routeNotificationForMail($notification = null): ?string
    {
        return $this->billingSnapshot()['email'] ?? null;
    }

    public function notifyCustomer(\Illuminate\Notifications\Notification $notification): void
    {
        if (! $this->routeNotificationForMail()) return;
        if (method_exists($notification, 'afterCommit')) $notification->afterCommit();
        $this->notify($notification);
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class)->withTrashed();
    }

    /** Kupón vydaný zákazníkovi za túto objednávku („Získaj kupón“). */
    public function issuedCoupon()
    {
        return $this->hasOne(Coupon::class, 'source_order_id')->withTrashed();
    }

    /**
     * Riadok z adresára zákazníka, z ktorého adresa prišla.
     *
     * Len stopa pôvodu — čo sa naozaj doručuje, hovorí odtlačok v `delivery_*`.
     */
    public function customerAddress()
    {
        return $this->belongsTo(CustomerAddress::class);
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

    public function orderReturns()
    {
        return $this->hasMany(OrderReturn::class);
    }

    public function mark()
    {
        return $this->morphOne(Mark::class, 'fileable');
    }

    /** Podklady nahrané zákazníkom v košíku (logá, návrhy, objednávkové súbory). */
    public function attachments()
    {
        return $this->morphMany(Attachment::class, 'attachable');
    }

    /**
     * Má objednávka vlastnú doručovaciu adresu, inú než sídlo zákazníka?
     *
     * Prázdna ulica znamená „doručiť na fakturačnú adresu" — tak vyzerajú
     * všetky objednávky spred tejto funkcie a väčšina objednávok vôbec.
     */
    public function hasCustomDelivery(): bool
    {
        return filled($this->delivery_street) && filled($this->delivery_city);
    }

    /**
     * Adresa, na ktorú sa balík posiela — vlastná, inak fakturačná.
     *
     * Vracia vždy plný tvar, aby volajúci (e-mail, dodací list, verejný detail)
     * nemusel riešiť, odkiaľ ktorý riadok pochádza.
     */
    public function deliverySnapshot(): array
    {
        $customer = $this->billing;

        if (! $this->hasCustomDelivery()) {
            return [
                'is_custom' => false,
                'company'   => $customer?->company,
                'name'      => $this->name ?: $customer?->name,
                'street'    => $customer?->street,
                'postcode'  => AddressFormatter::formatPostcode($customer?->postcode),
                'city'      => $customer?->city,
                'country'   => 'SK',
                'phone'     => $this->phone ?: $customer?->phone,
                'note'      => null,
            ];
        }

        return [
            'is_custom' => true,
            'company'   => $this->delivery_company ?: $customer?->company,
            'name'      => $this->delivery_name,
            'street'    => $this->delivery_street,
            'postcode'  => AddressFormatter::formatPostcode($this->delivery_postcode),
            'city'      => $this->delivery_city,
            'country'   => $this->delivery_country ?: 'SK',
            'phone'     => $this->delivery_phone,
            'note'      => $this->delivery_note,
        ];
    }

    /**
     * Ide balík na fakturačnú adresu? Aj ručne zadaná adresa doručenia môže
     * byť tá istá — vtedy ju v e-maile netreba ukazovať dvakrát.
     */
    public function deliveryMatchesBilling(): bool
    {
        if (! $this->hasCustomDelivery()) {
            return true;
        }

        $customer = $this->billing;
        $norm = fn ($v) => mb_strtolower(preg_replace('/\s+/', '', (string) $v));

        return $customer
            && $norm($this->delivery_street) === $norm($customer->street)
            && $norm($this->delivery_postcode) === $norm($customer->postcode)
            && $norm($this->delivery_city) === $norm($customer->city);
    }

    /**
     * Kým sa adresa smie meniť.
     *
     * Po expedícii je neskoro — balík je na ceste a prepísaná adresa by v
     * systéme klamala o tom, kam sa poslal. Stornovanej a archivovanej
     * objednávky sa to netýka vôbec.
     */
    public function canEditDelivery(): bool
    {
        if ($this->trashed()) {
            return false;
        }

        return in_array(OrderStatus::fromOrder($this), [
            OrderStatus::Draft,
            OrderStatus::Processing,
            OrderStatus::ReadyToShip,
        ], true);
    }

    /** Platí ešte odkaz z e-mailu? Tokenu vyprší platnosť skôr, než sa naň zabudne. */
    public function hasValidDeliveryToken(?string $token): bool
    {
        if (blank($token) || blank($this->delivery_token)) {
            return false;
        }

        if ($this->delivery_token_expires_at && $this->delivery_token_expires_at->isPast()) {
            return false;
        }

        return hash_equals($this->delivery_token, $token);
    }

    /** Verejný detail objednávky — odkaz z e-mailu. */
    public function publicUrl(): string
    {
        return rtrim(config('app.frontend_url'), '/')."/objednavka/{$this->uuid}";
    }

    /** Verejná zmena adresy doručenia — odkaz z e-mailu, chránený tokenom. */
    public function deliveryEditUrl(): ?string
    {
        if (blank($this->delivery_token)) {
            return null;
        }

        return $this->publicUrl().'/adresa?token='.$this->delivery_token;
    }

    public function priceSum()
    {
        return round($this->orderProducts->sum('total'), 2);
    }

    public function getProductOrderSumAttribute()
    {
        return $this->orderProducts->sum('quantity');
    }

    public function isStorned()
    {
        if ($this->status === OrderStatus::Cancelled) {
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
        // Ak je relácia eager-loadnutá (napr. v zozname objednávok), sčítaj z pamäte;
        // inak agreguj priamo v DB namiesto hydratácie všetkých riadkov skladu.
        if ($this->relationLoaded('stocks')) {
            return (int) $this->stocks->sum('quantity');
        }

        return (int) $this->stocks()->sum('quantity');
    }

    public function scopeFilter($query, $filters)
    {
        return $filters->apply($query);
    }
}
