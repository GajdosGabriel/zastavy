<?php

namespace App\Http\Controllers\Api\Dashboard;

use App\Models\Order;
use App\Models\OrderReturn;
use App\Models\Stock;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use App\Http\Controllers\Controller;
use App\Http\Resources\OrderReturnResource;
use App\Http\Resources\OrderResource;
use App\Notifications\OrderReturnProcessed;

class OrderReturnController extends Controller
{
    public function index(Order $order)
    {
        Gate::authorize('view', $order);

        $returns = $order->orderReturns()
            ->with(['items.orderProduct.product', 'createdBy', 'processedBy'])
            ->latest()
            ->get();

        return OrderReturnResource::collection($returns);
    }

    public function store(Order $order, Request $request)
    {
        Gate::authorize('update', $order);

        $validated = $request->validate([
            'reason'          => ['required', 'in:not_accepted,damaged,wrong_item,other'],
            'note'            => ['nullable', 'string', 'max:1000'],
            'items'           => ['required', 'array', 'min:1'],
            'items.*.order_product_id' => ['required', 'integer', 'exists:order_products,id'],
            'items.*.quantity'         => ['required', 'integer', 'min:1'],
        ]);

        // Validate items belong to this order and quantity doesn't exceed shipped
        foreach ($validated['items'] as $item) {
            $op = $order->orderProducts()->with('stocks')->find($item['order_product_id']);
            abort_if(! $op, 422, 'Položka nepatrí k tejto objednávke.');
            abort_if($item['quantity'] > $op->stockSum, 422, "Množstvo na vrátenie ({$item['quantity']}) prekračuje expedované množstvo ({$op->stockSum}) pre {$op->product->name}.");
        }

        $orderReturn = DB::transaction(function () use ($order, $validated, $request) {
            $return = $order->orderReturns()->create([
                'reason'     => $validated['reason'],
                'note'       => $validated['note'] ?? null,
                'status'     => 'pending',
                'created_by' => $request->user()->id,
            ]);

            foreach ($validated['items'] as $item) {
                $return->items()->create([
                    'order_product_id' => $item['order_product_id'],
                    'quantity'         => $item['quantity'],
                ]);
            }

            return $return;
        });

        $orderReturn->load(['items.orderProduct.product', 'createdBy']);

        return new OrderReturnResource($orderReturn);
    }

    public function show(Order $order, OrderReturn $orderReturn)
    {
        Gate::authorize('view', $order);
        abort_if($orderReturn->order_id !== $order->id, 404);

        $orderReturn->load(['items.orderProduct.product', 'createdBy', 'processedBy']);

        return new OrderReturnResource($orderReturn);
    }

    public function update(Order $order, OrderReturn $orderReturn, Request $request)
    {
        Gate::authorize('update', $order);
        abort_if($orderReturn->order_id !== $order->id, 404);
        abort_if(! $orderReturn->isPending(), 422, 'Vrátenie nie je možné upraviť — nie je v stave "čaká".');

        $validated = $request->validate([
            'reason' => ['sometimes', 'in:not_accepted,damaged,wrong_item,other'],
            'note'   => ['nullable', 'string', 'max:1000'],
            'items'  => ['sometimes', 'array', 'min:1'],
            'items.*.order_product_id' => ['required_with:items', 'integer', 'exists:order_products,id'],
            'items.*.quantity'         => ['required_with:items', 'integer', 'min:1'],
        ]);

        DB::transaction(function () use ($order, $orderReturn, $validated) {
            $orderReturn->update([
                'reason' => $validated['reason'] ?? $orderReturn->reason,
                'note'   => array_key_exists('note', $validated) ? $validated['note'] : $orderReturn->note,
            ]);

            if (isset($validated['items'])) {
                foreach ($validated['items'] as $item) {
                    $op = $order->orderProducts()->with('stocks')->find($item['order_product_id']);
                    abort_if(! $op, 422, 'Položka nepatrí k tejto objednávke.');
                    abort_if($item['quantity'] > $op->stockSum, 422, "Množstvo prekračuje expedované množstvo pre {$op->product->name}.");
                }

                $orderReturn->items()->delete();
                foreach ($validated['items'] as $item) {
                    $orderReturn->items()->create($item);
                }
            }
        });

        $orderReturn->load(['items.orderProduct.product', 'createdBy', 'processedBy']);

        return new OrderReturnResource($orderReturn);
    }

    public function destroy(Order $order, OrderReturn $orderReturn)
    {
        Gate::authorize('update', $order);
        abort_if($orderReturn->order_id !== $order->id, 404);
        abort_if(! $orderReturn->isPending(), 422, 'Nie je možné zmazať spracované vrátenie.');

        $orderReturn->delete();

        return response()->noContent();
    }

    public function process(Order $order, OrderReturn $orderReturn, Request $request)
    {
        Gate::authorize('update', $order);
        abort_if($orderReturn->order_id !== $order->id, 404);
        abort_if(! $orderReturn->isPending(), 422, 'Vrátenie je už spracované alebo zrušené.');

        DB::transaction(function () use ($order, $orderReturn, $request) {
            $orderReturn->load('items.orderProduct.stocks');

            foreach ($orderReturn->items as $returnItem) {
                Stock::create([
                    'order_id'         => $order->id,
                    'order_product_id' => $returnItem->order_product_id,
                    'order_return_id'  => $orderReturn->id,
                    'shipping_id'      => null,
                    'quantity'         => -$returnItem->quantity,
                ]);
            }

            $orderReturn->update([
                'status'       => 'processed',
                'processed_by' => $request->user()->id,
                'processed_at' => now(),
            ]);
        });

        $order->refresh()->load(['customer.users', 'user', 'shippings.notices', 'orderProducts.stocks', 'orderReturns.items.orderProduct.product']);

        $orderReturn->load(['items.orderProduct.product', 'createdBy', 'processedBy']);

        if ($request->boolean('notify_customer')) {
            $customer = $order->customer;
            if ($customer?->email) {
                $customer->notify(new OrderReturnProcessed($order, $orderReturn));
            }
        }

        return (new OrderReturnResource($orderReturn))->additional([
            'order' => new OrderResource($order),
        ]);
    }

    public function cancel(Order $order, OrderReturn $orderReturn)
    {
        Gate::authorize('update', $order);
        abort_if($orderReturn->order_id !== $order->id, 404);
        abort_if(! $orderReturn->isPending(), 422, 'Vrátenie je už spracované alebo zrušené.');

        $orderReturn->update(['status' => 'cancelled']);

        $orderReturn->load(['items.orderProduct.product', 'createdBy', 'processedBy']);

        return new OrderReturnResource($orderReturn);
    }
}
