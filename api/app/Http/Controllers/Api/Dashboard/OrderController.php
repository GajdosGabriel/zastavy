<?php

namespace App\Http\Controllers\Api\Dashboard;

use App\Enums\ModelStatus;
use App\Models\Order;
use App\Models\Stock;
use App\Filters\OrderFilter;
use App\Http\Requests\OrderRequest;
use App\Http\Controllers\Controller;
use App\Http\Resources\OrderResource;
use App\Notifications\OrderCreated;
use App\Services\CustomerService;
use App\Actions\StoreOrder;
use App\Http\Resources\OrderStatisticResource;
use App\Services\OrderStatisticsService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Notification;


class OrderController extends Controller
{
    private const DASHBOARD_ROLES = ['super-admin', 'admin', 'manager', 'sales', 'warehouse'];

    public function index(OrderFilter $orderFilters)
    {
        $orders = app(OrderStatisticsService::class)
            ->queryFor(request()->user(), $orderFilters)
            ->with(['customer.users', 'user'])
            ->orderBy('created_at', 'desc')
            ->paginate();

        return OrderResource::collection($orders);
    }

    public function statistics(OrderFilter $orderFilters, OrderStatisticsService $statistics)
    {
        return new OrderStatisticResource(
            $statistics->handle(request()->user(), $orderFilters)
        );
    }

    public function show(Order $order)
    {
        Gate::authorize('view', $order);

        return response(new OrderResource($order->load(['customer.users', 'user'])));
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
        };

        Gate::authorize('update', $order);

        $order->update($request->all());
        return new OrderResource($order);
    }



    public function store(OrderRequest $request)
    {
        Gate::authorize('create', Order::class);

        $notifyCustomer = $request->user()->hasRole('super-admin')
            && $request->boolean('notify_customer');

        [$order] = DB::transaction(function () use ($request) {
            [$customer, $user] = (new CustomerService)->handleCheckout($request->input('customer'));
            $order = (new StoreOrder($request))->handle($customer, $user);

            return [$order->load(['customer.users', 'user', 'orderProducts'])];
        });

        if ($notifyCustomer) {
            $order->loadMissing('user');
            Notification::send(collect([$order->user])->filter()->all(), new OrderCreated($order));
        }

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
