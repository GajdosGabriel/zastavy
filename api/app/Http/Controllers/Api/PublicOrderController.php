<?php

namespace App\Http\Controllers\Api;

use App\Models\Order;
use App\Http\Controllers\Controller;

class PublicOrderController extends Controller
{
    public function show(string $uuid)
    {
        $order = Order::where('uuid', $uuid)
            ->with(['customer', 'orderProducts.product', 'shippingMethod', 'paymentMethod'])
            ->firstOrFail();

        $subtotal = $order->orderProducts->sum('total');
        $shipping = (float) ($order->shipping_price ?? 0);
        $fee      = (float) ($order->payment_fee ?? 0);
        $discount = (float) ($order->discount_amount ?? 0);

        return response()->json([
            'data' => [
                'uuid'          => $order->uuid,
                'serial_number' => $order->serial_number,
                'created_at'    => $order->created_at,
                'note'          => $order->note,
                'customer' => [
                    'name'     => $order->customer->name,
                    'company'  => $order->customer->company,
                    'ico'      => $order->customer->ico,
                    'dic'      => $order->customer->dic,
                    'ic_dic'   => $order->customer->ic_dic,
                    'email'    => $order->customer->email,
                    'phone'    => $order->customer->phone,
                    'street'   => $order->customer->street,
                    'city'     => $order->customer->city,
                    'postcode' => $order->customer->postcode,
                ],
                'shipping_method' => $order->shippingMethod ? [
                    'name'  => $order->shippingMethod->name,
                    'price' => $shipping,
                ] : null,
                'payment_method' => $order->paymentMethod ? [
                    'name' => $order->paymentMethod->name,
                    'fee'  => $fee,
                ] : null,
                'order_products' => $order->orderProducts->map(fn($op) => [
                    'name'     => $op->product->name ?? '—',
                    'quantity' => $op->quantity,
                    'price'    => $op->price,
                    'total'    => $op->total,
                ]),
                'subtotal'        => $subtotal,
                'shipping_price'  => $shipping,
                'payment_fee'     => $fee,
                'discount_amount' => $discount,
                'grand_total'     => $subtotal + $shipping + $fee - $discount,
            ],
        ]);
    }
}
