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
        $orders = Order::orderBy('created_at', 'desc')->filter($orderFilters)->paginate();
        return OrderResource::collection($orders);
    }

    public function show(Order $order)
    {
        return response(new OrderResource($order));
    }

    public function update(Order $order, OrderRequest $request)
    {
        if ($request->makeStorned) {

            foreach ($order->orderProducts as $product) {
                if ($product->storno == 0) {
                    $calculator = $product->quantity - Stock::where('order_product_id', '=', $product->id)->get()->sum('quantity');
                } else {
                    $calculator = 0;
                }

                $product->update([
                    'storno' => $calculator
                ]);
            }
            return new OrderResource($order);
        };

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
