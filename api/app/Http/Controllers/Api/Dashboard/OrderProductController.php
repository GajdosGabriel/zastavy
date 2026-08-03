<?php

namespace App\Http\Controllers\Api\Dashboard;

use App\Models\Order;
use App\Models\OrderProduct;
use App\Models\ProductVariant;
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
            'product_variant_id' => ['required', 'integer', 'exists:product_variants,id'],
            'quantity'           => ['required', 'integer', 'min:1'],
            'price'              => ['required', 'numeric', 'min:0'],
        ]);

        $variant = ProductVariant::findOrFail($request->product_variant_id);

        $orderProduct = $order->orderProducts()->create([
            'product_id'         => $variant->product_id,
            'product_variant_id' => $variant->id,
            'variant_label'      => $variant->name,
            'quantity'           => $request->quantity,
            'price'              => $request->price,
            'total'              => (float) $request->quantity * (float) $request->price,
            'storno'             => 0,
        ]);

        $orderProduct->load(['product', 'variant']);

        return new OrderProductResource($orderProduct);
    }

    public function update(Order $order, $orderProduct, Request $request)
    {
        Gate::authorize('update', $order);

        $data = $request->only(['product_id', 'quantity', 'storno', 'price']);

        if (isset($data['quantity']) && isset($data['price'])) {
            $data['total'] = (float) $data['quantity'] * (float) $data['price'];
        }

        OrderProduct::firstOrCreate(['id' => $orderProduct])->update($data);

        return response()->noContent();
    }

    public function destroy(Order $order, OrderProduct $orderProduct)
    {
        Gate::authorize('delete', $orderProduct);

        $orderProduct->delete();
        return response()->noContent();
    }
}
