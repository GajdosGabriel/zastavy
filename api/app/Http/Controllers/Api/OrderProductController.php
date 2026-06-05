<?php

namespace App\Http\Controllers\Api;

use App\Models\Order;
use App\Models\OrderProduct;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Http\Resources\OrderProductResource;

class OrderProductController extends Controller
{
    public function index(Order $order)
    {
        return OrderProductResource::collection($order->orderProducts);
    }

    public function update(Order $order, $orderProduct, Request $request)
    {
        $orderProduct = OrderProduct::firstOrCreate([
            'id' => $orderProduct
        ])->update($request->only(['product_id', 'quantity', 'storno', 'price']));
        return response()->noContent();
    }

    public function destroy(Order $order, OrderProduct $orderProduct)
    {
        $orderProduct->delete();
        return response()->noContent();
    }
}
