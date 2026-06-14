<?php

namespace App\Actions;

use App\Models\Coupon;
use App\Models\Order;
use App\Models\User;
use App\Models\ShippingMethod;
use App\Notifications\OrderCreated;
use Illuminate\Http\Request;
use App\Contracts\StoreOrderContract;
use App\Models\PaymentMethod;
use Illuminate\Support\Facades\Notification;


class StoreOrder implements StoreOrderContract
{

    function __construct(Request $request)
    {
        $this->request = $request;
    }


    public function handle($customer, $user = null)
    {
        $contact = $this->request->input('customer', []);

        [$shippingMethodId, $shippingPrice, $paymentMethodId, $paymentFee, $couponId, $discountAmount] =
            $this->resolveCheckoutFields();

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
        $this->serialNumber($customer, $order);
        $this->storeOrderProducts($order);

        if ($couponId) {
            Coupon::where('id', $couponId)->increment('used_count');
        }

        $this->notifyOrderCreated($order);

        return $order;
    }

    protected function resolveCheckoutFields(): array
    {
        $shippingMethodId = $this->request->input('shipping_method_id');
        $paymentMethodId  = $this->request->input('payment_method_id');
        $couponCode       = $this->request->input('coupon_code');

        $shippingPrice = 0.0;
        if ($shippingMethodId) {
            $method = ShippingMethod::find($shippingMethodId);
            $cartTotal = collect($this->request->input('orderProducts', []))
                ->sum(fn ($p) => ($p['active_price'] ?? 0) * ($p['input_order'] ?? 0));
            $shippingPrice = $method ? $method->resolvePrice((float) $cartTotal) : 0.0;
        }

        $paymentFee = 0.0;
        if ($paymentMethodId) {
            $method = PaymentMethod::find($paymentMethodId);
            $paymentFee = $method ? (float) $method->fee : 0.0;
        }

        $couponId = null;
        $discountAmount = 0.0;
        if ($couponCode) {
            $coupon = Coupon::where('code', strtoupper($couponCode))->first();
            if ($coupon) {
                $cartTotal ??= collect($this->request->input('orderProducts', []))
                    ->sum(fn ($p) => ($p['active_price'] ?? 0) * ($p['input_order'] ?? 0));
                $couponId = $coupon->id;
                $discountAmount = $coupon->calculateDiscount((float) $cartTotal);
            }
        }

        return [$shippingMethodId, $shippingPrice, $paymentMethodId, $paymentFee, $couponId, $discountAmount];
    }

    protected function storeOrderProducts($order)
    {
        new StoreOrderProduct($order, $this->request->orderProducts);
    }

    protected function notifyOrderCreated(Order $order): void
    {
        $order->load(['customer', 'orderProducts.product', 'shippingMethod', 'paymentMethod']);

        $notification = new OrderCreated($order);

        if ($order->customer?->email) {
            $order->customer->notify($notification);
        }

        Notification::send(User::role('super-admin')->get(), $notification);
    }

    protected function serialNumber($customer, $order)
    {
        if ($customer->ico) {

            $countOrders = Order::whereHas('customer', function ($query) use ($customer) {
                $query->whereIco($customer->ico);
            })->count();
        }

        $idPostfix = $countOrders ?? 1;
        $nextId =  STR_PAD((string)$idPostfix, 3, "0", STR_PAD_LEFT) . '/' . date("Y");
        $order->update(['serial_number' => $nextId]);
    }
}
