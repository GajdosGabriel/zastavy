<?php

namespace App\Http\Controllers\Api\SuperAdmin;

use App\Models\Shipping;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Notifications\OrderExpedition;
use Illuminate\Support\Facades\Notification;

class ShippingNoticeController extends Controller
{
    public function store(Shipping $shipping, Request $request)
    {
        $shipping->notices()->create(['notice' => $request->input('notifyType')]);

        if (request()->notifyType == 'email') {
            $shipping->loadMissing('order.user');
            Notification::send(collect([$shipping->order->user])->filter()->all(), new OrderExpedition($shipping->order));
          };
    }
}
