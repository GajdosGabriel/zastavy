<?php

namespace App\Http\Controllers\Api\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Services\DashboardService;
use Illuminate\Support\Facades\Gate;

class DashboardController extends Controller
{
    public function index(DashboardService $dashboard)
    {
        Gate::authorize('viewAny', Order::class);

        return response()->json(['data' => $dashboard->handle(request()->user())]);
    }
}
