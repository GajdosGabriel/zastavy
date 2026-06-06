<?php

namespace App\Http\Controllers\Api\Dashboard;

use App\Models\Order;
use App\Models\Stock;
use App\Filters\OrderFilter;
use App\Http\Requests\OrderRequest;
use App\Http\Controllers\Controller;
use App\Http\Resources\OrderResource;
use App\Notifications\OrderCreated;
use App\Services\CustomerService;
use App\Actions\StoreOrder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Notification;


class OrderController extends Controller
{
    private const DASHBOARD_ROLES = ['super-admin', 'admin', 'manager', 'sales', 'warehouse'];

    public function index(OrderFilter $orderFilters)
    {
        $orders = Order::with(['customer.users', 'user'])
            ->orderBy('created_at', 'desc')
            ->when(! request()->user()->hasAnyRole(self::DASHBOARD_ROLES), function ($query) {
                $query->where(function ($query) {
                    $query->where('user_id', request()->user()->id);

                    if (request()->user()->customer_id) {
                        $query->orWhere('customer_id', request()->user()->customer_id);
                    }
                });
            })
            ->filter($orderFilters)
            ->paginate();

        return OrderResource::collection($orders);
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

            foreach ($order->orderProducts as $product) {
                $shippedQuantity = Stock::where('order_product_id', '=', $product->id)->sum('quantity');
                $stornoQuantity = max(0, $product->quantity - $shippedQuantity);

                $product->update([
                    'storno' => $stornoQuantity
                ]);
            }
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
