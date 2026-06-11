<?php

namespace App\Http\Controllers\Api;

use App\Models\Coupon;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class CouponController extends Controller
{
    public function validate(Request $request)
    {
        $request->validate([
            'code'       => 'required|string',
            'cart_total' => 'required|numeric|min:0',
        ]);

        $coupon = Coupon::where('code', strtoupper($request->code))->first();

        if (! $coupon) {
            return response()->json(['message' => 'Kupón neexistuje.'], 404);
        }

        if (! $coupon->isValid((float) $request->cart_total)) {
            return response()->json(['message' => 'Kupón nie je platný alebo nesplňuje podmienky.'], 422);
        }

        return response()->json([
            'data' => [
                'id'       => $coupon->id,
                'code'     => $coupon->code,
                'type'     => $coupon->type,
                'value'    => $coupon->value,
                'discount' => $coupon->calculateDiscount((float) $request->cart_total),
            ],
        ]);
    }
}
