<?php

namespace App\Http\Controllers\Api\SuperAdmin;

use App\Models\Customer;
use App\Http\Controllers\Controller;
use App\Http\Resources\OrderIndexResource;

class CustomerOrderController extends Controller
{
    public function index(Customer $customer)
    {
        $orders = $customer->orders()
            ->with(['customer', 'shippings.stocks', 'shippings.notices', 'orderProducts', 'stocks', 'mark', 'notices'])
            ->paginate();

        return OrderIndexResource::collection($orders);
    }
}
