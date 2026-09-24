<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Services\ReorderService;
use Illuminate\Support\Facades\Gate;

class ReorderController extends Controller
{
    public function show(Order $order, ReorderService $service)
    {
        Gate::authorize('view', $order);

        return response()->json(['items' => $service->preview($order)]);
    }

    public function publicShow(string $uuid, ReorderService $service)
    {
        return response()->json(['items' => $service->preview(Order::where('uuid', $uuid)->firstOrFail())]);
    }
}
