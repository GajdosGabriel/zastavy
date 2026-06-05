<?php

namespace App\Http\Controllers\Api;


use App\Models\Customer;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Http\Resources\CustomerResource;

class CustomerMarkController extends Controller
{
    public function store(Customer $customer)
    {
        if ($customer->mark) {
            $customer->mark()->delete();
        } else {
            $customer->mark()->create(['user_id' => auth()->id()]);
        }
        
        $customer = Customer::whereId($customer->id)->first();
        return response(new CustomerResource($customer));
    }
}
