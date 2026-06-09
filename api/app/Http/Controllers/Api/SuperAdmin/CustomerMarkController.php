<?php

namespace App\Http\Controllers\Api\SuperAdmin;

use App\Models\Customer;
use App\Http\Controllers\Controller;
use App\Http\Resources\CustomerResource;
use Illuminate\Support\Facades\Gate;

class CustomerMarkController extends Controller
{
    public function store(Customer $customer)
    {
        Gate::authorize('update', $customer);

        if ($customer->mark) {
            $customer->mark()->delete();
        } else {
            $customer->mark()->create(['user_id' => auth()->id()]);
        }

        $customer = Customer::whereId($customer->id)->first();

        return response(new CustomerResource($customer));
    }
}
