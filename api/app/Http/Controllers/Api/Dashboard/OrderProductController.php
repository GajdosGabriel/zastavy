<?php

namespace App\Http\Controllers\Api\Dashboard;

use App\Models\Order;
use App\Models\OrderProduct;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Http\Resources\OrderProductResource;
use Illuminate\Support\Facades\Gate;

class OrderProductController extends Controller
{
    public function index(Order $order)
    {
        Gate::authorize('view', $order);

        return OrderProductResource::collection($order->orderProducts);
    }

    public function store(Order $order, Request $request)
    {
        Gate::authorize('update', $order);

        $request->validate([
            'product_id' => ['required', 'integer', 'exists:products,id'],
            'quantity'   => ['required', 'integer', 'min:1'],
            'price'      => ['required', 'numeric', 'min:0'],
        ]);

        $orderProduct = $order->orderProducts()->create([
            'product_id' => $request->product_id,
            'quantity'   => $request->quantity,
            'price'      => $request->price,
            'storno'     => 0,
        ]);

        $orderProduct->load('product');

        return new OrderProductResource($orderProduct);
    }

    public function update(Order $order, $orderProduct, Request $request)
    {
        Gate::authorize('update', $order);

        $orderProduct = OrderProduct::firstOrCreate([
            'id' => $orderProduct
        ])->update($request->only(['product_id', 'quantity', 'storno', 'price']));
        return response()->noContent();
    }

    public function destroy(Order $order, OrderProduct $orderProduct)
    {
        Gate::authorize('delete', $orderProduct);

        $orderProduct->delete();
        return response()->noContent();
    }
}
