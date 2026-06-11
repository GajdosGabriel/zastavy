<?php

namespace App\Http\Controllers\Api\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Actions\StoreImage;
use App\Http\Resources\ProductResource;
use App\Models\Image;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class ProductImageController extends Controller
{
    public function store(Product $product, Request $request)
    {
        $request->validate([
            'images' => ['required', 'array'],
            'images.*' => ['file', 'image', 'max:5120'],
        ]);

        new StoreImage($product, $request->file('images'));

        return new ProductResource($product->refresh()->load(['images', 'categories']));
    }

    public function reorder(Product $product, Request $request)
    {
        $request->validate([
            'ids'   => ['required', 'array'],
            'ids.*' => ['integer'],
        ]);

        foreach ($request->ids as $position => $id) {
            $product->images()->whereKey($id)->update(['sort_order' => $position]);
        }

        return new ProductResource($product->refresh()->load(['images', 'categories']));
    }

    public function destroy(Product $product, Image $image)
    {
        Gate::authorize('delete', $image);

        $product->images()->whereKey($image->id)->delete();

        return response()->noContent();
    }
}
