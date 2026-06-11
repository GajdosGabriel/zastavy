<?php

namespace App\Http\Controllers\Api;

use App\Models\ShippingMethod;
use App\Http\Controllers\Controller;

class ShippingMethodController extends Controller
{
    public function index()
    {
        return response()->json([
            'data' => ShippingMethod::where('active', true)->orderBy('sort_order')->orderBy('name')->get(),
        ]);
    }
}
