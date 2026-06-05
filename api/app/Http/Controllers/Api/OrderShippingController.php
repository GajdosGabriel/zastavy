<?php

namespace App\Http\Controllers\Api;


use App\Models\Order;
use Illuminate\Http\Request;
use App\Services\ShippingService;
use App\Http\Controllers\Controller;
use App\Http\Resources\ShippingResource;
use App\Http\Resources\OrderResource;

class OrderShippingController extends Controller
{
    public function store(Order $order, Request $request)
    {
        $order->update(['isOpened' => 1]);
        
        $shipping =  (new ShippingService)->create($order);

        return (new ShippingResource($shipping))->additional([
            'order' => new OrderResource($order)
        ]);
    }
}
