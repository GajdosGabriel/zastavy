<?php

namespace App\Http\Controllers\Api\SuperAdmin;

use App\Filters\StockFilter;
use App\Http\Controllers\Controller;
use App\Http\Resources\StockResource;
use App\Models\ProductVariant;
use App\Models\Stock;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class StockController extends Controller
{
    public function index(StockFilter $stockFilters)
    {
        Gate::authorize('viewAny', Stock::class);

        $stocks = Stock::with([
                'shipping.order.customer',
                'orderProduct.product',
                'orderProduct.variant',
                'productDirect',
                'variant',
            ])
            ->latest()
            ->filter($stockFilters)
            ->paginate();

        return StockResource::collection($stocks);
    }

    public function summary()
    {
        Gate::authorize('viewAny', Stock::class);

        // Príjmy — evidujú sa priamo na variante (skladovej položke).
        $totalIn = DB::table('stocks')
            ->whereNull('shipping_id')
            ->whereNotNull('product_variant_id')
            ->whereNull('deleted_at')
            ->groupBy('product_variant_id')
            ->select('product_variant_id', DB::raw('SUM(quantity) as total'))
            ->pluck('total', 'product_variant_id');

        // Výdaje — variant sa dohľadá cez položku objednávky.
        $totalOut = DB::table('stocks')
            ->join('order_products', 'stocks.order_product_id', '=', 'order_products.id')
            ->whereNotNull('stocks.shipping_id')
            ->whereNotNull('order_products.product_variant_id')
            ->whereNull('stocks.deleted_at')
            ->groupBy('order_products.product_variant_id')
            ->select('order_products.product_variant_id', DB::raw('SUM(stocks.quantity) as total'))
            ->pluck('total', 'product_variant_id');

        $variantIds = collect($totalIn->keys())->merge($totalOut->keys())->unique();

        $variants = ProductVariant::withTrashed()
            ->whereIn('id', $variantIds)
            ->with('product:id,name,unit_value')
            ->get();

        $summary = $variants->map(function (ProductVariant $variant) use ($totalIn, $totalOut) {
            $in  = (int) ($totalIn[$variant->id]  ?? 0);
            $out = (int) ($totalOut[$variant->id] ?? 0);

            return [
                'product_id'         => $variant->product_id,
                'product_variant_id' => $variant->id,
                'code'               => $variant->code,
                'name'               => $variant->product?->name,
                'variant_name'       => $variant->name,
                'unit_value'         => $variant->product?->unit_value,
                'total_in'           => $in,
                'total_out'          => $out,
                'balance'            => $in - $out,
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

        $data = $request->validate([
            'product_variant_id' => 'required|integer|exists:product_variants,id',
            'quantity'           => 'required|integer',
            'price'              => 'nullable|numeric|min:0',
            'note'               => 'nullable|string|max:255',
        ]);

        // product_id sa dopĺňa z variantu — príjem nesmie skončiť na produkte
        // bez určenia, ktorej skladovej položky sa týka.
        $data['product_id'] = ProductVariant::whereKey($data['product_variant_id'])->value('product_id');

        $stock = Stock::create($data);

        return response(new StockResource($stock->load(['productDirect', 'variant'])), 201);
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
