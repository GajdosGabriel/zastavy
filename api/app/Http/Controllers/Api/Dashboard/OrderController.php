<?php

namespace App\Http\Controllers\Api\Dashboard;

use App\Enums\ModelStatus;
use App\Models\Order;
use App\Models\Stock;
use App\Filters\OrderFilter;
use App\Http\Requests\OrderRequest;
use App\Http\Controllers\Controller;
use App\Http\Resources\OrderIndexResource;
use App\Http\Resources\OrderResource;
use App\Http\Resources\OrderStatisticResource;
use App\Services\CustomerService;
use App\Services\OrderStatisticsService;
use App\Actions\StoreOrder;
use App\Models\PaymentMethod;
use App\Models\ShippingMethod;
use App\Notifications\OrderUpdated;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class OrderController extends Controller
{
    public function index(OrderFilter $orderFilters)
    {
        Gate::authorize('viewAny', Order::class);

        $orders = app(OrderStatisticsService::class)
            ->queryFor(request()->user(), $orderFilters)
            ->with(['customer', 'shippings.stocks', 'shippings.notices', 'orderProducts', 'stocks', 'mark'])
            ->orderBy('created_at', 'desc')
            ->paginate();

        return OrderIndexResource::collection($orders);
    }

    public function statistics(OrderFilter $orderFilters, OrderStatisticsService $statistics)
    {
        Gate::authorize('viewAny', Order::class);

        return new OrderStatisticResource(
            $statistics->handle(request()->user(), $orderFilters)
        );
    }

    public function show(Order $order)
    {
        Gate::authorize('view', $order);

        return response(new OrderResource($order->load(['customer.users', 'user', 'shippingMethod', 'paymentMethod'])));
    }

    public function update(Order $order, OrderRequest $request)
    {
        if ($request->makeStorned) {
            Gate::authorize('storno', $order);

            DB::transaction(function () use ($order) {
                $order->forceFill(['status' => ModelStatus::Cancelled])->save();

                foreach ($order->orderProducts as $product) {
                    $shippedQuantity = Stock::where('order_product_id', '=', $product->id)->sum('quantity');
                    $stornoQuantity = max(0, $product->quantity - $shippedQuantity);
                    $remainingQuantity = max(0, $product->quantity - $product->storno - $shippedQuantity);

                    $product->update([
                        'storno' => $stornoQuantity,
                        'status' => $remainingQuantity > 0 ? ModelStatus::Cancelled : $product->status,
                    ]);
                }
            });

            return new OrderResource($order->refresh()->load(['customer.users', 'user']));
        }

        Gate::authorize('update', $order);

        $changes = $this->detectChanges($order, $request);

        $order->update($request->only([
            'shipping_method_id', 'payment_method_id', 'status',
            'isOpened', 'isDelivered',
        ]));

        if ($request->boolean('notify_customer') && !empty($changes)) {
            $notifiable = $order->user ?? $order->customer;
            $notifiable?->notify(new OrderUpdated($order->refresh()->load('orderProducts.product', 'customer'), $changes));
        }

        return new OrderResource($order);
    }

    private function detectChanges(Order $order, $request): array
    {
        $changes = [];

        if ($request->filled('shipping_method_id') && $request->shipping_method_id != $order->shipping_method_id) {
            $oldMethod = $order->shippingMethod?->name ?? '—';
            $newMethod = ShippingMethod::find($request->shipping_method_id)?->name ?? '—';
            $changes[] = ['label' => 'Spôsob dopravy', 'old' => $oldMethod, 'new' => $newMethod];
        }

        if ($request->filled('payment_method_id') && $request->payment_method_id != $order->payment_method_id) {
            $oldMethod = $order->paymentMethod?->name ?? '—';
            $newMethod = PaymentMethod::find($request->payment_method_id)?->name ?? '—';
            $changes[] = ['label' => 'Spôsob platby', 'old' => $oldMethod, 'new' => $newMethod];
        }

        return $changes;
    }

    public function store(OrderRequest $request)
    {
        Gate::authorize('create', Order::class);

        [$order] = DB::transaction(function () use ($request) {
            [$customer, $user] = (new CustomerService)->handleCheckout($request->input('customer'));
            $order = (new StoreOrder($request))->handle($customer, $user);

            return [$order->load(['customer.users', 'user', 'orderProducts'])];
        });

        return new OrderResource($order->refresh()->load(['customer.users', 'user', 'orderProducts']));
    }

    public function destroy(Order $order)
    {
        Gate::authorize('delete', $order);

        $order->orderProducts()->delete();
        $order->delete();

        return response(new OrderResource($order));
    }
}
