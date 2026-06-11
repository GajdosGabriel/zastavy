<?php

namespace App\Http\Controllers\Api;

use App\Models\PaymentMethod;
use App\Http\Controllers\Controller;

class PaymentMethodController extends Controller
{
    public function index()
    {
        return response()->json([
            'data' => PaymentMethod::where('active', true)->orderBy('sort_order')->orderBy('name')->get(),
        ]);
    }
}
