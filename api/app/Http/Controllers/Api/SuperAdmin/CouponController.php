<?php

namespace App\Http\Controllers\Api\SuperAdmin;

use App\Models\Coupon;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class CouponController extends Controller
{
    public function index()
    {
        return response()->json([
            'data'    => Coupon::latest()->get(),
            'trashed' => Coupon::onlyTrashed()->latest()->get(),
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'code'            => 'required|string|max:50|unique:coupons,code',
            'type'            => 'required|in:percent,fixed',
            'value'           => 'required|numeric|min:0',
            'min_order_price' => 'nullable|numeric|min:0',
            'usage_limit'     => 'nullable|integer|min:1',
            'valid_from'      => 'nullable|date',
            'valid_to'        => 'nullable|date|after_or_equal:valid_from',
            'active'          => 'boolean',
        ]);

        $data['code'] = strtoupper($data['code']);

        return response()->json(['data' => Coupon::create($data)], 201);
    }

    public function update(Request $request, Coupon $coupon)
    {
        $data = $request->validate([
            'code'            => 'required|string|max:50|unique:coupons,code,' . $coupon->id,
            'type'            => 'required|in:percent,fixed',
            'value'           => 'required|numeric|min:0',
            'min_order_price' => 'nullable|numeric|min:0',
            'usage_limit'     => 'nullable|integer|min:1',
            'valid_from'      => 'nullable|date',
            'valid_to'        => 'nullable|date|after_or_equal:valid_from',
            'active'          => 'boolean',
        ]);

        $data['code'] = strtoupper($data['code']);
        $coupon->update($data);

        return response()->json(['data' => $coupon]);
    }

    public function destroy(Coupon $coupon)
    {
        $coupon->delete();

        return response()->json(null, 204);
    }

    public function restore(int $id)
    {
        $coupon = Coupon::onlyTrashed()->findOrFail($id);
        $coupon->restore();

        return response()->json(['data' => $coupon]);
    }
}
