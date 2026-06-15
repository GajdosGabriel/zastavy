<?php

namespace App\Http\Controllers\Api\SuperAdmin;

use App\Filters\StockFilter;
use App\Http\Controllers\Controller;
use App\Http\Resources\StockResource;
use App\Models\Product;
use App\Models\Stock;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class StockController extends Controller
{
    public function index(StockFilter $stockFilters)
    {
        Gate::authorize('viewAny', Stock::class);

        $stocks = Stock::with(['shipping.order.customer', 'orderProduct.product', 'productDirect'])
            ->latest()
            ->filter($stockFilters)
            ->paginate();

        return StockResource::collection($stocks);
    }

    public function summary()
    {
        Gate::authorize('viewAny', Stock::class);

        // Incoming receipts grouped by product_id
        $totalIn = DB::table('stocks')
            ->whereNull('shipping_id')
            ->whereNotNull('product_id')
            ->whereNull('deleted_at')
            ->groupBy('product_id')
            ->select('product_id', DB::raw('SUM(quantity) as total'))
            ->pluck('total', 'product_id');

        // Outgoing expeditions grouped by the product linked through order_products
        $totalOut = DB::table('stocks')
            ->join('order_products', 'stocks.order_product_id', '=', 'order_products.id')
            ->whereNotNull('stocks.shipping_id')
            ->whereNull('stocks.deleted_at')
            ->groupBy('order_products.product_id')
            ->select('order_products.product_id', DB::raw('SUM(stocks.quantity) as total'))
            ->pluck('total', 'product_id');

        $productIds = collect($totalIn->keys())->merge($totalOut->keys())->unique();

        $products = Product::whereIn('id', $productIds)
            ->get(['id', 'code', 'name', 'unit_value']);

        $summary = $products->map(function ($product) use ($totalIn, $totalOut) {
            $in  = (int) ($totalIn[$product->id]  ?? 0);
            $out = (int) ($totalOut[$product->id] ?? 0);
            return [
                'product_id' => $product->id,
                'code'       => $product->code,
                'name'       => $product->name,
                'unit_value' => $product->unit_value,
                'total_in'   => $in,
                'total_out'  => $out,
                'balance'    => $in - $out,
            ];
        })->sortByDesc('total_out')->values();

        return response()->json(['data' => $summary]);
    }

    public function show(Stock $stock)
    {
        Gate::authorize('view', $stock);

        return response(new StockResource($stock->load(['shipping.order.customer', 'orderProduct.product', 'productDirect'])));
    }

    public function store(Request $request)
    {
        Gate::authorize('create', Stock::class);

        $stock = Stock::create($request->only(['product_id', 'quantity', 'price', 'note']));

        return response(new StockResource($stock->load('productDirect')), 201);
    }

    public function update(Stock $stock, Request $request)
    {
        Gate::authorize('update', $stock);

        $stock->update($request->all());

        return new StockResource($stock);
    }

    public function destroy(Stock $stock)
    {
        Gate::authorize('delete', $stock);

        $stock->delete();

        return response()->noContent();
    }
}
