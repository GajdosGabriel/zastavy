<?php

namespace App\Actions;

use App\Models\Coupon;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use App\Models\ShippingMethod;
use App\Notifications\OrderCreated;
use App\Services\Delivery\DeliveryAddressService;
use Illuminate\Http\Request;
use App\Contracts\StoreOrderContract;
use App\Models\PaymentMethod;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;


class StoreOrder implements StoreOrderContract
{

    public function __construct(private Request $request)
    {

    }


    public function handle($customer, $user = null)
    {
        $contact = $this->request->input('customer', []);

        // Ceny sa berú z databázy, nie z requestu — klientom poslané ceny sa ignorujú.
        $items = $this->resolveItems();
        $cartTotal = round($items->sum(fn ($item) => $item['price'] * $item['quantity']), 2);

        [$shippingMethodId, $shippingPrice, $paymentMethodId, $paymentFee, $couponId, $discountAmount] =
            $this->resolveCheckoutFields($cartTotal);

        $delivery = $this->resolveDelivery($customer);

        $order = $customer->orders()->create($delivery + [
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
        $order->update(['serial_number' => app(\App\Services\OrderNumberService::class)->next($order->created_at->format('Y-m'))]);
        $this->storeOrderProducts($order, $items);
        $this->storeAttachments($order, $user);

        if ($couponId) {
            Coupon::where('id', $couponId)->increment('used_count');
        }

        $this->notifyOrderCreated($order);

        return $order;
    }

    /**
     * Doručovacia adresa objednávky.
     *
     * Prázdne stĺpce znamenajú „doručiť na sídlo zákazníka" — tak ide väčšina
     * objednávok a tak vyzerá aj celá história spred tejto funkcie.
     *
     * Zaškrtnuté „uložiť adresu" pridá riadok do adresára zákazníka; objednávka
     * si aj tak nesie vlastný odtlačok, takže neskoršia úprava adresára ju
     * neprepíše.
     */
    protected function resolveDelivery($customer): array
    {
        $service = app(DeliveryAddressService::class);

        $snapshot = $service->resolve(
            $customer,
            $this->request->input('delivery'),
            $this->request->input('customer_address_id') ? (int) $this->request->input('customer_address_id') : null,
        );

        $wantsSave = filter_var($this->request->input('delivery.save_address'), FILTER_VALIDATE_BOOLEAN);

        if ($wantsSave && $snapshot['customer_address_id'] === null && filled($snapshot['delivery_street'])) {
            $address = $service->remember($customer, $snapshot, $this->request->input('delivery.label'));
            $snapshot['customer_address_id'] = $address?->id;
        }

        return $snapshot;
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

        return $requested->map(function ($item, $index) use ($variants, $fallbackVariants, $isStaff) {
            $variant = isset($item['variant_id'])
                ? $variants->get($item['variant_id'])
                : $fallbackVariants->get($item['id'] ?? null)?->defaultVariant;

            if (! $variant || (int) $variant->product_id !== (int) $item['id']) {
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
            if ((int) $item['input_order'] < $minOrder) {
                throw ValidationException::withMessages(["orderProducts.$index.input_order" => ["Minimálne objednávané množstvo je $minOrder."]]);
            }

            return [
                'product_snapshot' => [
                    'name' => $variant->product->name, 'code' => $variant->product->code,
                    'unit_value' => $variant->product->unit_value ?? 'ks', 'vat' => $variant->product->vat,
                    'variant_name' => $variant->name, 'variant_code' => $variant->code,
                ],
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
            $method = ShippingMethod::where('active', true)->lockForUpdate()->find($shippingMethodId);
            if (! $method) throw ValidationException::withMessages(['shipping_method_id' => ['Vyberte dostupný spôsob dopravy.']]);
            $shippingPrice = $method ? $method->resolvePrice($cartTotal) : 0.0;
        }

        $paymentFee = 0.0;
        if ($paymentMethodId) {
            $method = PaymentMethod::where('active', true)->lockForUpdate()->find($paymentMethodId);
            if (! $method) throw ValidationException::withMessages(['payment_method_id' => ['Vyberte dostupný spôsob úhrady.']]);
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

            $notification->afterCommit();
            if ($this->shouldNotifyCustomer()) {
                $order->notifyCustomer($notification);
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

}
