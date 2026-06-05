<?php

namespace App\Http\Controllers\Api;

use App\Models\Stock;
use App\Filters\StockFilter;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Http\Resources\StockResource;

class StockController extends Controller
{
    public function index(StockFilter $stockFilters)
    {
        $stocks = Stock::latest()->filter($stockFilters)->paginate();
        return StockResource::collection($stocks);
    }

    public function show(Stock $stock)
    {
        return response(new StockResource($stock));
    }

    public function create(Request $request)
    {
    }

    public function update(Stock $stock, Request $request)
    {
        $stock->update($request->all());
        return new StockResource($stock);
    }

    public function store(Request $request)
    {
        $stock = Stock::create($request->all());
        return response(new StockResource($stock));
    }

    public function destroy(Stock $stock)
    {
        $stock->delete();
        return response()->noContent();
    }
}
