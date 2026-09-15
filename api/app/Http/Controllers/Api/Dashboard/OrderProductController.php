<?php

namespace App\Http\Controllers\Api\Dashboard;

use App\Models\Order;
use App\Models\OrderProduct;
use App\Models\ProductVariant;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Http\Resources\OrderProductResource;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\DB;

class OrderProductController extends Controller
{
    public function index(Order $order)
    {
        Gate::authorize('view', $order);

        return OrderProductResource::collection($order->orderProducts);
    }

    public function store(Order $order, Request $request)
    {
        Gate::authorize('manageItems', $order);

        $request->validate([
            'product_variant_id' => ['required', 'integer', 'exists:product_variants,id'],
            'quantity'           => ['required', 'integer', 'min:1'],
            'price'              => ['required', 'numeric', 'min:0'],
        ]);

        $variant = ProductVariant::findOrFail($request->product_variant_id);

        $orderProduct = DB::transaction(function () use ($order, $variant, $request) {
            $order = Order::whereKey($order->id)->lockForUpdate()->firstOrFail();
            return $order->orderProducts()->create([
                'product_id'         => $variant->product_id,
                'product_variant_id' => $variant->id,
                'variant_label'      => $variant->name,
                'quantity'           => $request->quantity,
                'price'              => $request->price,
                'total'              => (float) $request->quantity * (float) $request->price,
                'storno'             => 0,
            ]);
        });
        $orderProduct->load(['product', 'variant']);

        return new OrderProductResource($orderProduct);
    }

    public function update(Order $order, $orderProduct, Request $request)
    {
        Gate::authorize('manageItems', $order);

        $item = $order->orderProducts()->findOrFail($orderProduct);
        $data = $request->validate([
            'product_id' => ['sometimes', 'integer', \Illuminate\Validation\Rule::in([$item->product_id])],
            'quantity' => ['sometimes', 'integer', 'min:1', 'max:100000'],
            'storno' => ['sometimes', 'integer', 'min:0'],
            'price' => ['sometimes', 'numeric', 'min:0'],
        ]);
        unset($data['product_id']);
        DB::transaction(function () use ($order, $orderProduct, $data) {
            Order::whereKey($order->id)->lockForUpdate()->firstOrFail();
            $item = $order->orderProducts()->findOrFail($orderProduct);
            $quantity = (int) ($data['quantity'] ?? $item->quantity);
            $storno = (int) ($data['storno'] ?? $item->storno);
            abort_if($storno + $item->stockSum > $quantity, 422, 'Množstvo nesmie byť menšie než expedované a stornované kusy.');
            $data['total'] = round($quantity * (float) ($data['price'] ?? $item->price), 2);
            $item->update($data);
        });

        return response()->noContent();
    }

    public function destroy(Order $order, OrderProduct $orderProduct)
    {
        Gate::authorize('manageItems', $order);
        abort_unless($orderProduct->order_id === $order->id, 404);
        Gate::authorize('delete', $orderProduct);

        DB::transaction(function () use ($order, $orderProduct) {
            Order::whereKey($order->id)->lockForUpdate()->firstOrFail();
            $item = $order->orderProducts()->findOrFail($orderProduct->id);
            abort_if($item->stocks()->exists(), 422, 'Položku so skladovou históriou nemožno zmazať.');
            $item->delete();
        });
        return response()->noContent();
    }
}
