<?php

namespace App\Http\Controllers\Api\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\Shipping;
use App\Notifications\OrderExpedition;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class ShippingNoticeController extends Controller
{
    public function store(Shipping $shipping, Request $request)
    {
        Gate::authorize('shippings.notices');

        $notifyType = $request->input('notifyType', 'email');

        $shipping->notices()->create(['notice' => $notifyType]);

        if ($notifyType === 'email') {
            $shipping->loadMissing([
                'order.customer',
                'order.user',
                'order.shippingMethod',
                'order.paymentMethod',
                'order.orderProducts.product',
                'order.orderProducts.stocks',
            ]);

            $shipping->order->notifyCustomer(new OrderExpedition($shipping->order, $shipping));
        }

        return response()->noContent();
    }
}
