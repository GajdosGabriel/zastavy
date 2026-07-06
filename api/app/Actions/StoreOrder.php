<?php

namespace App\Actions;

use App\Models\Coupon;
use App\Models\Order;
use App\Models\Product;
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

        if ($couponId) {
            Coupon::where('id', $couponId)->increment('used_count');
        }

        $this->notifyOrderCreated($order);

        return $order;
    }

    /**
     * Načíta produkty z databázy a vráti položky objednávky so serverovou cenou.
     */
    protected function resolveItems(): Collection
    {
        $requested = collect($this->request->input('orderProducts', []));

        // Verejný e-shop smie objednať iba publikované produkty (aj cez priame ID
        // v requeste). Interný staff môže do objednávky pridať aj nepublikované.
        $products = Product::whereIn('id', $requested->pluck('id')->filter())
            ->when(! $this->isStaffRequest(), fn ($query) => $query->where('published', 1))
            ->get()
            ->keyBy('id');

        return $requested->map(function ($item) use ($products) {
            $product = $products->get($item['id'] ?? null);

            if (! $product) {
                throw ValidationException::withMessages([
                    'orderProducts' => ['Niektorý z produktov v košíku už nie je dostupný.'],
                ]);
            }

            // Minimálne odberné množstvo sa vynucuje na serveri, nielen v UI.
            $minOrder = max(1, (int) ($product->min_order ?? 1));

            return [
                'product_id' => $product->id,
                'quantity'   => max($minOrder, (int) ($item['input_order'] ?? 0)),
                'price'      => (float) $product->active_price,
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

            if ($order->customer?->email) {
                $order->customer->notify($notification);
            }

            Notification::send(User::role('super-admin')->get(), $notification);
        } catch (\Throwable $e) {
            // Zlyhanie notifikácie nesmie zhodiť vytvorenie objednávky.
            report($e);
        }
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
