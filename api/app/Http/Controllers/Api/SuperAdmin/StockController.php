<?php

namespace App\Http\Controllers\Api\SuperAdmin;

use App\Filters\StockFilter;
use App\Http\Controllers\Controller;
use App\Http\Resources\StockResource;
use App\Models\Stock;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class StockController extends Controller
{
    public function index(StockFilter $stockFilters)
    {
        Gate::authorize('viewAny', Stock::class);

        $stocks = Stock::latest()->filter($stockFilters)->paginate();

        return StockResource::collection($stocks);
    }

    public function show(Stock $stock)
    {
        Gate::authorize('view', $stock);

        return response(new StockResource($stock));
    }

    public function store(Request $request)
    {
        Gate::authorize('create', Stock::class);

        $stock = Stock::create($request->all());

        return response(new StockResource($stock));
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
