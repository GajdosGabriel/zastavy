<?php

namespace App\Http\Controllers\Api;

use App\Models\Order;
use App\Models\Stock;
use App\Models\Product;
use App\Models\Customer;
use App\Filters\OrderFilter;
use Illuminate\Http\Request;
use App\Http\Requests\OrderRequest;
use App\Http\Controllers\Controller;
use App\Http\Resources\OrderResource;
use Illuminate\Support\Facades\Gate;


class OrderController extends Controller
{

    public function index(OrderFilter $orderFilters)
    {
        $orders = Order::with(['customer.users', 'user'])
            ->orderBy('created_at', 'desc')
            ->when(! request()->user()->hasRole('super-admin'), function ($query) {
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
        $customer = Customer::whereIco($request->ico)->first();

        if (!$customer) {
            $customer = Customer::create($request->except('notice'));
        }

        $order = $customer->orders()->create();

        // dd($request->session()->get('carts'));

        $cartData = $request->session()->get('carts');

        foreach ($cartData as $key => $value) {
            $product = Product::whereId($key)->firstOrFail();
            $price = $value['quantity'] * $product->price;

            $order->orderProducts()->create([
                'product_id' => $key,
                'quantity' => $value['quantity'],
                'price' => $price
            ]);
        }

        session()->forget('carts');
        session()->forget('total');

        return redirect()->route('home.index');
    }


    public function destroy(Order $order)
    {
        Gate::authorize('delete', $order);
        $order->orderProducts()->delete();
        $order->delete();
        return response(new OrderResource($order));
    }
}
