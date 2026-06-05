<?php

namespace App\Http\Controllers\Api;


use App\Models\Order;
use Illuminate\Http\Request;
use App\Services\ShippingService;
use App\Http\Controllers\Controller;
use App\Http\Resources\ShippingResource;
use App\Http\Resources\OrderResource;
use App\Notifications\OrderExpedition;
use Illuminate\Support\Facades\Notification;

class OrderShippingController extends Controller
{
    public function store(Order $order, Request $request)
    {
        $validated = $request->validate([
            'notify_customer' => ['sometimes', 'boolean'],
        ]);

        $notifyCustomer = $validated['notify_customer'] ?? true;

        $order->update(['isOpened' => 1]);
        
        $shipping =  (new ShippingService)->create($order);

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
