<?php

namespace App\Http\Controllers\Api\SuperAdmin;

use App\Models\ShippingMethod;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class ShippingMethodController extends Controller
{
    public function index()
    {
        return response()->json([
            'data'    => ShippingMethod::orderBy('sort_order')->orderBy('name')->get(),
            'trashed' => ShippingMethod::onlyTrashed()->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name'            => 'required|string|max:255',
            'price'           => 'required|numeric|min:0',
            'free_from_price' => 'nullable|numeric|min:0',
            'active'          => 'boolean',
            'sort_order'      => 'nullable|integer|min:0',
        ]);

        return response()->json(['data' => ShippingMethod::create($data)], 201);
    }

    public function update(Request $request, ShippingMethod $shippingMethod)
    {
        $data = $request->validate([
            'name'            => 'required|string|max:255',
            'price'           => 'required|numeric|min:0',
            'free_from_price' => 'nullable|numeric|min:0',
            'active'          => 'boolean',
            'sort_order'      => 'nullable|integer|min:0',
        ]);

        $shippingMethod->update($data);

        return response()->json(['data' => $shippingMethod]);
    }

    public function destroy(ShippingMethod $shippingMethod)
    {
        $shippingMethod->delete();

        return response()->json(null, 204);
    }

    public function restore(int $id)
    {
        $method = ShippingMethod::onlyTrashed()->findOrFail($id);
        $method->restore();

        return response()->json(['data' => $method]);
    }
}
