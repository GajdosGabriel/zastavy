<?php

namespace App\Http\Controllers\Api\SuperAdmin;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class CouponSettingsController extends Controller
{
    private const FILE = 'coupon_settings.json';

    private const DEFAULTS = [
        'delay_days'           => 14,
        'valid_days'           => 30,
        'discount_percent'     => 10,
        'min_order_multiplier' => 1.5,
        'min_order_floor'      => 50,
    ];

    public function show(): JsonResponse
    {
        return response()->json(['data' => $this->read()]);
    }

    public function update(Request $request): JsonResponse
    {
        $data = $request->validate([
            'delay_days'           => 'required|integer|min:0|max:90',
            'valid_days'           => 'required|integer|min:7|max:365',
            'discount_percent'     => 'required|integer|min:1|max:50',
            'min_order_multiplier' => 'required|numeric|min:1|max:5',
            'min_order_floor'      => 'required|numeric|min:0',
        ]);

        Storage::put(self::FILE, json_encode($data, JSON_PRETTY_PRINT));

        return response()->json(['data' => $data]);
    }

    public static function read(): array
    {
        if (! Storage::exists(self::FILE)) {
            return self::DEFAULTS;
        }

        $decoded = json_decode(Storage::get(self::FILE), true);

        return is_array($decoded) ? array_merge(self::DEFAULTS, $decoded) : self::DEFAULTS;
    }
}
