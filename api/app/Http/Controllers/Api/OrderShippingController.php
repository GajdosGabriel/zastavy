<?php

namespace App\Http\Controllers\Api;


use App\Models\Order;
use Illuminate\Http\Request;
use App\Services\ShippingService;
use App\Http\Controllers\Controller;
use App\Http\Resources\ShippingResource;
use App\Http\Resources\OrderResource;
use App\Notifications\OrderExpedition;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

class OrderShippingController extends Controller
{
    public function store(Order $order, Request $request)
    {
        $validated = $request->validate([
            'notify_customer' => ['sometimes', 'boolean'],
            'items' => ['sometimes', 'array'],
            'items.*.order_product_id' => ['required_with:items', 'integer', 'exists:order_products,id'],
            'items.*.quantity' => ['required_with:items', 'integer', 'min:0'],
        ]);

        $notifyCustomer = $validated['notify_customer'] ?? true;
        $items = $validated['items'] ?? null;

        $shipping = DB::transaction(function () use ($order, $items) {
            $order->load('orderProducts.stocks');
            $order->update(['isOpened' => 1]);

            return (new ShippingService)->create($order, $items);
        });

        if ($notifyCustomer && $shipping) {
            $shipping->notices()->create(['notice' => 'email']);
            Notification::send([$order->customer], new OrderExpedition($order));
        }

        $shipping?->load('notices');
        $order->refresh()->load(['customer', 'shippings.notices', 'orderProducts', 'stocks']);

        return (new ShippingResource($shipping))->additional([
            'order' => new OrderResource($order)
        ]);
    }
}
