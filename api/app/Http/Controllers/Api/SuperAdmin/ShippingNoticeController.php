<?php

namespace App\Http\Controllers\Api\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\Shipping;
use App\Notifications\OrderExpedition;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Notification;

class ShippingNoticeController extends Controller
{
    public function store(Shipping $shipping, Request $request)
    {
        Gate::authorize('shippings.notices');

        $shipping->notices()->create(['notice' => $request->input('notifyType')]);

        if ($request->notifyType == 'email') {
            $shipping->loadMissing('order.user');
            Notification::send(collect([$shipping->order->user])->filter()->all(), new OrderExpedition($shipping->order));
        }
    }
}
