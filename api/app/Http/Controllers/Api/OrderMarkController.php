<?php

namespace App\Http\Controllers\Api;

use App\Models\Order;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Http\Resources\OrderResource;

class OrderMarkController extends Controller
{

    public function store(Order $order)
    {
        if ($order->mark) {
            $order->mark()->delete();
        } else {
            $order->mark()->create(['user_id' => auth()->id()]);
        }
        return new OrderResource(Order::whereId($order->id)->first());
    }
}
