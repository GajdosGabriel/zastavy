<?php

namespace App\Http\Controllers\Api\SuperAdmin;

use App\Models\Product;
use App\Actions\StoreImage;
use App\Filters\ProductFilter;
use App\Http\Controllers\Controller;
use App\Http\Requests\ProductRequest;
use App\Http\Resources\ProductResource;
use Illuminate\Support\Facades\Gate;

class ProductController extends Controller
{
    public function index(ProductFilter $productFilter)
    {
        Gate::authorize('viewAny', Product::class);

        $products = Product::with(['variants', 'defaultVariant'])
            ->filter($productFilter)
            ->paginate();

        return ProductResource::collection($products);
    }

    public function show(Product $product)
    {
        return response(new ProductResource($product->load([
            'images',
            'categories',
            'variants.attributeValues.attribute',
            'defaultVariant',
            'attributesTaxonomy.values',
        ])));
    }

    public function store(ProductRequest $request)
    {
        Gate::authorize('create', Product::class);

        // categories je väzba, nie stĺpec — do create/update nesmie vojsť.
        $product = Product::create($request->safe()->except('categories'));
        $product->categories()->sync($request->input('categories', []));

        new StoreImage($product, $request->images);

        return new ProductResource($product);
    }

    public function update(Product $product, ProductRequest $request)
    {
        Gate::authorize('update', $product);

        $product->update($request->safe()->except('categories'));
        $product->categories()->sync($request->input('categories', []));

        return new ProductResource($product->refresh()->load(['images', 'categories']));
    }

    public function destroy(Product $product)
    {
        Gate::authorize('delete', $product);

        $product->delete();

        return response(new ProductResource($product));
    }
}
