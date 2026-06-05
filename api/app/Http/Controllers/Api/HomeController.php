<?php

namespace App\Http\Controllers\Api;

use App\Models\Product;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Http\Resources\ProductResource;

class HomeController extends Controller
{
    public function index()
    {
        return ProductResource::collection(Product::wherePublished(1)->paginate());
    }

    public function show(Product $product)
    {
        return response(new ProductResource($product));
    }
}
