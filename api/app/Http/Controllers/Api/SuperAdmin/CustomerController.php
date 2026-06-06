<?php

namespace App\Http\Controllers\Api\SuperAdmin;

use App\Models\Customer;
use Illuminate\Http\Request;
use App\Filters\CustomerFilter;
use App\Http\Controllers\Controller;
use App\Http\Requests\CustomerRequest;
use App\Http\Resources\CustomerResource;
use App\Http\Requests\CustomerCreateRequest;
use App\Http\Requests\CustomerUpdateRequest;
use App\Services\CustomerService;
use Illuminate\Support\Facades\Gate;

class CustomerController extends Controller
{
    public function index(CustomerFilter $customerFilter)
    {
        $customers = Customer::with('users')->filter($customerFilter)->latest()->paginate();

        return CustomerResource::collection($customers);
    }

    public function show(Customer $customer)
    {
        return response(new CustomerResource($customer->load('users')));
    }

    public function update(Customer $customer, CustomerUpdateRequest $request)
    {
        (new CustomerService)->updateWithUser($customer, $request->except('orders'));

        // return response()->noContent();
        return new CustomerResource($customer->load('users'));
    }


    public function store(CustomerCreateRequest $request)
    {

        [$customer] = (new CustomerService)->handleCheckout($request->all());

        // return response()->noContent();
        return new CustomerResource($customer->load('users'));
    }

    public function destroy(Customer $customer)
    {
        Gate::authorize('delete', $customer);

        $customer->delete();
        
        return response(new CustomerResource($customer));
    }
}
