<?php

namespace App\Http\Controllers\Api\SuperAdmin;

use App\Models\PaymentMethod;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class PaymentMethodController extends Controller
{
    public function index()
    {
        return response()->json([
            'data'    => PaymentMethod::orderBy('sort_order')->orderBy('name')->get(),
            'trashed' => PaymentMethod::onlyTrashed()->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name'   => 'required|string|max:255',
            'fee'    => 'required|numeric|min:0',
            'type'   => 'required|in:card,bank_transfer,cash_on_delivery',
            'active' => 'boolean',
            'sort_order' => 'nullable|integer|min:0',
        ]);

        return response()->json(['data' => PaymentMethod::create($data)], 201);
    }

    public function update(Request $request, PaymentMethod $paymentMethod)
    {
        $data = $request->validate([
            'name'   => 'required|string|max:255',
            'fee'    => 'required|numeric|min:0',
            'type'   => 'required|in:card,bank_transfer,cash_on_delivery',
            'active' => 'boolean',
            'sort_order' => 'nullable|integer|min:0',
        ]);

        $paymentMethod->update($data);

        return response()->json(['data' => $paymentMethod]);
    }

    public function destroy(PaymentMethod $paymentMethod)
    {
        $paymentMethod->delete();

        return response()->json(null, 204);
    }

    public function restore(int $id)
    {
        $method = PaymentMethod::onlyTrashed()->findOrFail($id);
        $method->restore();

        return response()->json(['data' => $method]);
    }
}
