<?php

namespace App\Http\Controllers\Api\Dashboard;


use App\Actions\IssueCouponForOrder;
use App\Models\Order;
use Illuminate\Http\Request;
use App\Services\ShippingService;
use App\Http\Controllers\Controller;
use App\Http\Resources\ShippingResource;
use App\Http\Resources\OrderResource;
use App\Notifications\OrderExpedition;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class OrderShippingController extends Controller
{
    public function store(Order $order, Request $request)
    {
        Gate::authorize('ship', $order);

        $validated = $request->validate([
            'idempotency_key' => ['sometimes', 'uuid'],
            'notify_customer' => ['sometimes', 'boolean'],
            'items' => ['sometimes', 'array'],
            'items.*.order_product_id' => ['required_with:items', 'integer', 'distinct', 'exists:order_products,id'],
            'items.*.quantity' => ['required_with:items', 'integer', 'min:0'],
        ]);

        $notifyCustomer = $validated['notify_customer'] ?? true;
        $items = $validated['items'] ?? null;

        $shipping = (new ShippingService)->create($order, $items, $validated['idempotency_key'] ?? null);

        if ($notifyCustomer && $shipping?->wasRecentlyCreated) {
            $shipping->notices()->create(['notice' => 'email']);
            $order->loadMissing(['customer', 'shippingMethod', 'paymentMethod', 'orderProducts.product', 'orderProducts.stocks']);

            if ($order->routeNotificationForMail()) {
                $order->notifyCustomer(new OrderExpedition($order, $shipping));
            }
        }

        $shipping?->load('notices');
        $order->refresh()->load(['customer.users', 'user', 'shippings.notices', 'orderProducts', 'stocks']);

        if ($order->isFinished()) {
            $order->loadMissing(['customer', 'orderProducts']);
            (new IssueCouponForOrder)->handle($order);
        }

        return (new ShippingResource($shipping))->additional([
            'order' => new OrderResource($order)
        ]);
    }
}
