<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Image;
use App\Models\Product;
use Illuminate\Http\Request;

class ProductImageController extends Controller
{
    public function destroy(Product $product, Image $image)
    {
        $product->images()->delete($image->id);
        return response()->noContent();
    }
}
