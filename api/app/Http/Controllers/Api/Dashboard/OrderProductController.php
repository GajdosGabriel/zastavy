<?php

namespace App\Http\Controllers\Api\Dashboard;

use App\Models\Order;
use App\Models\OrderProduct;
use App\Models\ProductVariant;
use App\Rules\VatRule;
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
            'is_custom' => ['sometimes', 'boolean'],
            'name' => ['required_if:is_custom,true', 'string', 'max:200'],
            'unit_value' => ['nullable', 'string', 'max:20'],
            'vat' => ['required_if:is_custom,true', 'nullable', new VatRule()],
            'product_variant_id' => ['required_unless:is_custom,true', 'nullable', 'integer', 'exists:product_variants,id'],
            'quantity'           => ['required', 'integer', 'min:1'],
            'price'              => ['required', 'numeric', 'min:0'],
        ]);

        $variant = $request->boolean('is_custom') ? null : ProductVariant::findOrFail($request->product_variant_id);

        $orderProduct = DB::transaction(function () use ($order, $variant, $request) {
            $order = Order::whereKey($order->id)->lockForUpdate()->firstOrFail();
            return $order->orderProducts()->create([
                'is_custom' => $request->boolean('is_custom'),
                'product_snapshot' => $variant ? null : ['name' => $request->name, 'unit_value' => $request->unit_value ?: 'ks', 'code' => null, 'vat' => (int) $request->vat, 'variant_name' => null, 'variant_code' => null],
                'product_id'         => $variant?->product_id,
                'product_variant_id' => $variant?->id,
                'variant_label'      => $variant?->name,
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
            'name' => ['sometimes', 'required', 'string', 'max:200'],
            'unit_value' => ['sometimes', 'required', 'string', 'max:20'],
            'product_id' => ['sometimes', 'nullable', 'integer', \Illuminate\Validation\Rule::in([$item->product_id])],
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
            if ($item->is_custom) {
                $data['product_snapshot'] = array_replace($item->productSnapshot(), array_intersect_key($data, array_flip(['name', 'unit_value'])));
            }
            unset($data['name'], $data['unit_value']);
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
