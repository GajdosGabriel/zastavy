<?php

namespace App\Http\Controllers\Api;

use App\Models\Customer;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Http\Resources\OrderResource;

class CustomerOrderController extends Controller
{
    public function index(Customer $customer)
    {
        return OrderResource::collection($customer->orders);
    }
}
