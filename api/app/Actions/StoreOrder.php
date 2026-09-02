<?php

namespace App\Actions;

use App\Models\Coupon;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use App\Models\ShippingMethod;
use App\Notifications\OrderCreated;
use Illuminate\Http\Request;
use App\Contracts\StoreOrderContract;
use App\Models\PaymentMethod;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;


class StoreOrder implements StoreOrderContract
{

    function __construct(Request $request)
    {
        $this->request = $request;
    }


    public function handle($customer, $user = null)
    {
        $contact = $this->request->input('customer', []);

        // Ceny sa berú z databázy, nie z requestu — klientom poslané ceny sa ignorujú.
        $items = $this->resolveItems();
        $cartTotal = $items->sum(fn ($item) => $item['price'] * $item['quantity']);

        [$shippingMethodId, $shippingPrice, $paymentMethodId, $paymentFee, $couponId, $discountAmount] =
            $this->resolveCheckoutFields($cartTotal);

        $order = $customer->orders()->create([
            'user_id'            => $user?->id,
            'name'               => $contact['name'] ?? $user?->username ?? $customer->name,
            'email'              => $contact['email'] ?? $user?->email ?? $customer->email,
            'phone'              => $contact['phone'] ?? $user?->phone ?? $customer->phone,
            'shipping_method_id' => $shippingMethodId,
            'shipping_price'     => $shippingPrice,
            'payment_method_id'  => $paymentMethodId,
            'payment_fee'        => $paymentFee,
            'coupon_id'          => $couponId,
            'discount_amount'    => $discountAmount,
            'note'               => $this->request->input('note') ?: null,
            'wants_coupon'       => (bool) $this->request->input('wants_coupon', false),
        ]);
        $this->serialNumber($order);
        $this->storeOrderProducts($order, $items);
        $this->storeAttachments($order, $user);

        if ($couponId) {
            Coupon::where('id', $couponId)->increment('used_count');
        }

        $this->notifyOrderCreated($order);

        return $order;
    }

    /**
     * Podklady priložené v košíku (logo, návrh vlajky, tabuľka rozmerov).
     *
     * Ukladajú sa až po vytvorení objednávky, aby cesta obsahovala jej ID.
     * Beží vnútri transakcie checkoutu — ak objednávka spadne, DB záznamy
     * príloh sa vrátia späť (na disku ostane len osamotený súbor).
     */
    protected function storeAttachments(Order $order, $user = null): void
    {
        $files = $this->request->file('attachments');

        if (! $files) {
            return;
        }

        (new StoreAttachments())->handle($order, $files, $user?->id);
    }

    /**
     * Načíta varianty z databázy a vráti položky objednávky so serverovou cenou.
     *
     * Košík posiela variant_id. Ak chýba (staršia verzia frontendu), spadne sa
     * na default variant produktu — objednávka tak nikdy neostane bez skladovej
     * položky.
     */
    protected function resolveItems(): Collection
    {
        $requested = collect($this->request->input('orderProducts', []));
        $isStaff = $this->isStaffRequest();

        $variants = ProductVariant::whereIn('id', $requested->pluck('variant_id')->filter())
            ->with('product')
            ->get()
            ->keyBy('id');

        $fallbackVariants = Product::whereIn('id', $requested->pluck('id')->filter())
            ->with('defaultVariant')
            ->get()
            ->keyBy('id');

        return $requested->map(function ($item) use ($variants, $fallbackVariants, $isStaff) {
            $variant = isset($item['variant_id'])
                ? $variants->get($item['variant_id'])
                : $fallbackVariants->get($item['id'] ?? null)?->defaultVariant;

            if (! $variant) {
                throw ValidationException::withMessages([
                    'orderProducts' => ['Niektorá z položiek v košíku už nie je dostupná.'],
                ]);
            }

            // Verejný e-shop smie objednať iba publikovaný variant publikovaného
            // produktu. Interný staff môže do objednávky pridať aj nepublikované.
            if (! $isStaff && (! $variant->published || ! $variant->product?->published)) {
                throw ValidationException::withMessages([
                    'orderProducts' => ['Niektorá z položiek v košíku už nie je dostupná.'],
                ]);
            }

            // Minimálne odberné množstvo sa vynucuje na serveri, nielen v UI.
            $minOrder = max(1, (int) ($variant->min_order ?? 1));

            return [
                'product_id'         => $variant->product_id,
                'product_variant_id' => $variant->id,
                'variant_label'      => $variant->name,
                'quantity'           => max($minOrder, (int) ($item['input_order'] ?? 0)),
                'price'              => (float) $variant->active_price,
            ];
        });
    }

    protected function isStaffRequest(): bool
    {
        return (bool) $this->request->user('sanctum')
            ?->hasAnyRole(['super-admin', 'admin', 'manager', 'sales', 'warehouse']);
    }

    protected function resolveCheckoutFields(float $cartTotal): array
    {
        $shippingMethodId = $this->request->input('shipping_method_id');
        $paymentMethodId  = $this->request->input('payment_method_id');
        $couponCode       = $this->request->input('coupon_code');

        $shippingPrice = 0.0;
        if ($shippingMethodId) {
            $method = ShippingMethod::find($shippingMethodId);
            $shippingPrice = $method ? $method->resolvePrice($cartTotal) : 0.0;
        }

        $paymentFee = 0.0;
        if ($paymentMethodId) {
            $method = PaymentMethod::find($paymentMethodId);
            $paymentFee = $method ? (float) $method->fee : 0.0;
        }

        $couponId = null;
        $discountAmount = 0.0;
        if ($couponCode) {
            // lockForUpdate drží riadok kupónu do commitu transakcie, takže kontrola
            // usage_limit a následný increment sú atomické — limit sa nedá prekročiť
            // súbežnými objednávkami (race condition).
            $coupon = Coupon::where('code', strtoupper($couponCode))
                ->lockForUpdate()
                ->first();

            if (! $coupon || ! $coupon->isValid($cartTotal)) {
                throw ValidationException::withMessages([
                    'coupon_code' => ['Kupón nie je platný alebo nespĺňa podmienky.'],
                ]);
            }

            $couponId = $coupon->id;
            $discountAmount = $coupon->calculateDiscount($cartTotal);
        }

        return [$shippingMethodId, $shippingPrice, $paymentMethodId, $paymentFee, $couponId, $discountAmount];
    }

    protected function storeOrderProducts($order, Collection $items)
    {
        new StoreOrderProduct($order, $items);
    }

    protected function notifyOrderCreated(Order $order): void
    {
        try {
            $order->load(['customer', 'orderProducts.product', 'shippingMethod', 'paymentMethod']);

            $notification = new OrderCreated($order);

            if ($order->customer?->email && $this->shouldNotifyCustomer()) {
                $order->customer->notify($notification);
            }

            Notification::send(User::role('super-admin')->get(), $notification);
        } catch (\Throwable $e) {
            // Zlyhanie notifikácie nesmie zhodiť vytvorenie objednávky.
            report($e);
        }
    }

    /**
     * Potlačiť potvrdzovací e-mail smie iba interná obsluha, ktorá objednávku
     * zadáva za zákazníka. Verejný e-shop potvrdenie dostane vždy — request
     * z prehliadača nesmie vedieť e-mail zákazníkovi „vypnúť“.
     */
    protected function shouldNotifyCustomer(): bool
    {
        if (! $this->isStaffRequest()) {
            return true;
        }

        return $this->request->boolean('notify_customer', true);
    }

    protected function serialNumber(Order $order): void
    {
        $year  = $order->created_at->format('Y');
        $month = $order->created_at->format('m');

        // withTrashed: soft-deleted objednávky musia ostať v poradí,
        // inak sa po zmazaní pridelí už existujúce sériové číslo.
        $position = Order::withTrashed()
            ->whereYear('created_at', $year)
            ->whereMonth('created_at', $month)
            ->where('id', '<=', $order->id)
            ->count();

        $order->update([
            'serial_number' => "{$year}-{$month}-" . str_pad($position, 4, '0', STR_PAD_LEFT),
        ]);
    }
}
