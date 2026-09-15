<?php

namespace App\Http\Controllers\Api;

use App\Filters\ProductFilter;
use App\Models\Product;
use App\Http\Controllers\Controller;
use App\Http\Resources\ProductResource;

class HomeController extends Controller
{
    public function index(ProductFilter $productFilter)
    {
        $products = Product::wherePublished(1)
            ->with(['variants', 'defaultVariant', 'images'])
            ->filter($productFilter)
            ->paginate();

        return ProductResource::collection($products);
    }

    public function show(Product $product)
    {
        $staff = request()->user('sanctum');
        if (! $product->published) {
            abort_unless($staff?->isStaff() && $staff->can('view', $product), 404);
        }
        return response(new ProductResource($product->load([
            'images',
            'variants.attributeValues.attribute',
            'defaultVariant',
            'attributesTaxonomy.values',
        ])));
    }
}
