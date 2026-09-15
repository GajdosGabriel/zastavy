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
        Gate::authorize('manageReturns', $order);

        $validated = $request->validate([
            'reason'          => ['required', 'in:not_accepted,damaged,wrong_item,other'],
            // Poznámka chodí ako HTML z editora — značky sa rátajú do dĺžky.
            'note'            => ['nullable', 'string', 'max:3000'],
            'items'           => ['required', 'array', 'min:1'],
            'items.*.order_product_id' => ['required', 'integer', 'distinct', 'exists:order_products,id'],
            'items.*.quantity'         => ['required', 'integer', 'min:1'],
        ]);

        $orderReturn = DB::transaction(function () use ($order, $validated, $request) {
            $order = Order::whereKey($order->id)->lockForUpdate()->firstOrFail();
            $this->validateItems($order, $validated['items']);
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

        $orderReturn->refresh()->load(['items.orderProduct.product', 'createdBy', 'processedBy']);

        return new OrderReturnResource($orderReturn);
    }

    public function update(Order $order, OrderReturn $orderReturn, Request $request)
    {
        Gate::authorize('manageReturns', $order);
        abort_if($orderReturn->order_id !== $order->id, 404);
        abort_if(! $orderReturn->isPending(), 422, 'Vrátenie nie je možné upraviť — nie je v stave "čaká".');

        $validated = $request->validate([
            'reason' => ['sometimes', 'in:not_accepted,damaged,wrong_item,other'],
            'note'   => ['nullable', 'string', 'max:3000'],
            'items'  => ['sometimes', 'array', 'min:1'],
            'items.*.order_product_id' => ['required_with:items', 'integer', 'distinct', 'exists:order_products,id'],
            'items.*.quantity'         => ['required_with:items', 'integer', 'min:1'],
        ]);

        DB::transaction(function () use ($order, $orderReturn, $validated) {
            Order::whereKey($order->id)->lockForUpdate()->firstOrFail();
            $orderReturn = OrderReturn::whereKey($orderReturn->id)->lockForUpdate()->firstOrFail();
            abort_unless($orderReturn->isPending(), 422, 'Vrátenie už nie je v stave čaká.');
            $orderReturn->update([
                'reason' => $validated['reason'] ?? $orderReturn->reason,
                'note'   => array_key_exists('note', $validated) ? $validated['note'] : $orderReturn->note,
            ]);

            if (isset($validated['items'])) {
                $this->validateItems($order, $validated['items']);

                $orderReturn->items()->delete();
                foreach ($validated['items'] as $item) {
                    $orderReturn->items()->create($item);
                }
            }
        });

        $orderReturn->refresh()->load(['items.orderProduct.product', 'createdBy', 'processedBy']);

        return new OrderReturnResource($orderReturn);
    }

    public function destroy(Order $order, OrderReturn $orderReturn)
    {
        Gate::authorize('manageReturns', $order);
        abort_if($orderReturn->order_id !== $order->id, 404);
        abort_if(! $orderReturn->isPending(), 422, 'Nie je možné zmazať spracované vrátenie.');

        DB::transaction(function () use ($order, $orderReturn) {
            Order::whereKey($order->id)->lockForUpdate()->firstOrFail();
            $return = OrderReturn::whereKey($orderReturn->id)->lockForUpdate()->firstOrFail();
            abort_unless($return->isPending(), 422, 'Nie je možné zmazať spracované vrátenie.');
            $return->delete();
        });

        return response()->noContent();
    }

    public function process(Order $order, OrderReturn $orderReturn, Request $request)
    {
        Gate::authorize('manageReturns', $order);
        abort_if($orderReturn->order_id !== $order->id, 404);

        $request->validate(['restock' => ['sometimes', 'boolean']]);
        $processed = DB::transaction(function () use ($order, $orderReturn, $request) {
            Order::whereKey($order->id)->lockForUpdate()->firstOrFail();
            $orderReturn->refresh();
            if ($orderReturn->status === 'processed') {
                abort_if($request->has('restock') && $orderReturn->restocked !== $request->boolean('restock'), 409, 'Vrátenie už bolo spracované s iným zaradením do skladu.');
                return false;
            }
            abort_unless($orderReturn->isPending(), 422, 'Vrátenie je už spracované alebo zrušené.');
            $orderReturn->load('items.orderProduct.stocks');
            $this->validateItems($order, $orderReturn->items->toArray());
            $restock = $request->boolean('restock', $orderReturn->reason !== 'damaged');
            \App\Models\ProductVariant::withTrashed()->whereIn('id', $orderReturn->items->pluck('orderProduct.product_variant_id')->filter())
                ->orderBy('id')->lockForUpdate()->get();

            foreach ($orderReturn->items as $returnItem) {
                Stock::create([
                    'order_id'         => $order->id,
                    'order_product_id' => $returnItem->order_product_id,
                    'order_return_id'  => $orderReturn->id,
                    'shipping_id'      => null,
                    'quantity'         => -$returnItem->quantity,
                    'inventory_delta'  => $restock ? $returnItem->quantity : 0,
                ]);
            }

            $orderReturn->update([
                'status'       => 'processed',
                'processed_by' => $request->user()->id,
                'processed_at' => now(),
                'restocked' => $restock,
            ]);
            return true;
        });

        $order->refresh()->load(['customer.users', 'user', 'shippings.notices', 'orderProducts.stocks', 'orderReturns.items.orderProduct.product']);

        $orderReturn->refresh()->load(['items.orderProduct.product', 'createdBy', 'processedBy']);

        if ($processed && $request->boolean('notify_customer')) {
            $customer = $order->customer;
            if ($order->routeNotificationForMail()) {
                $order->notifyCustomer(new OrderReturnProcessed($order, $orderReturn));
            }
        }

        return (new OrderReturnResource($orderReturn))->additional([
            'order' => new OrderResource($order),
        ]);
    }

    public function cancel(Order $order, OrderReturn $orderReturn)
    {
        Gate::authorize('manageReturns', $order);
        abort_if($orderReturn->order_id !== $order->id, 404);
        abort_if(! $orderReturn->isPending(), 422, 'Vrátenie je už spracované alebo zrušené.');

        DB::transaction(function () use ($order, $orderReturn) {
            Order::whereKey($order->id)->lockForUpdate()->firstOrFail();
            $orderReturn->refresh();
            abort_unless($orderReturn->isPending(), 422, 'Vrátenie už nie je v stave čaká.');
            $orderReturn->update(['status' => 'cancelled']);
        });

        $orderReturn->refresh()->load(['items.orderProduct.product', 'createdBy', 'processedBy']);

        return new OrderReturnResource($orderReturn);
    }

    private function validateItems(Order $order, array $items): void
    {
        foreach (collect($items)->groupBy('order_product_id') as $id => $rows) {
            $item = $order->orderProducts()->find($id);
            abort_unless($item, 422, 'Položka nepatrí k objednávke.');
            abort_if($rows->sum('quantity') > $item->stocks()->sum('quantity'), 422, 'Množstvo prekračuje aktuálne expedované množstvo.');
        }
    }
}
