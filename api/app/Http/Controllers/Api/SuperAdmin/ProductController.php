<?php

namespace App\Http\Controllers\Api\SuperAdmin;

use Image;
use App\Models\Product;
use App\Actions\StoreImage;
use Illuminate\Support\Str;
use Illuminate\Http\Request;
use App\Filters\ProductFilter;
use App\Http\Controllers\Controller;
use App\Http\Requests\ProductRequest;
use App\Http\Resources\ProductResource;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Support\Facades\Gate;

class ProductController extends Controller
{

    public function index(ProductFilter $productFilter)
    {
        $products = Product::filter($productFilter)->paginate();
        return ProductResource::collection($products);
    }

    public function show(Product $product)
    {
        return response(new ProductResource($product->load(['images', 'categories'])));
    }

    public function update(Product $product, ProductRequest $request)
    {
        $product->update($request->validated());

        $product->categories()->sync($request->categories);

        // new StoreImage($product, $request->images);

        return new ProductResource($product->refresh()->load(['images', 'categories']));
    }

    public function store(ProductRequest $request)
    {
        $product =  Product::create($request->all());

        new StoreImage($product, $request->images);

        return new ProductResource($product);
    }

    public function destroy(Product $product)
    {
        Gate::authorize('delete', $product);

        $product->delete();
        return response(new ProductResource($product));
    }
}
