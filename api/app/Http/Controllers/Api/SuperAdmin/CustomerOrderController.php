<?php

namespace App\Http\Controllers\Api\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Http\Resources\OrderIndexResource;
use App\Models\Customer;
use Illuminate\Support\Facades\Gate;

class CustomerOrderController extends Controller
{
    public function index(Customer $customer)
    {
        Gate::authorize('view', $customer);

        $orders = $customer->orders()
            ->with(['customer', 'shippings.stocks', 'shippings.notices', 'orderProducts', 'stocks', 'mark'])
            ->paginate();

        return OrderIndexResource::collection($orders);
    }
}
