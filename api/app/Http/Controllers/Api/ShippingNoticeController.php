<?php

namespace App\Http\Controllers\Api;

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
            Notification::send([$shipping->order->customer], new OrderExpedition($shipping->order));
          };
    }
}
