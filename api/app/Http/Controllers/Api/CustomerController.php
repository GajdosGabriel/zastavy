<?php

namespace App\Http\Controllers\Api;

use App\Models\Customer;
use Illuminate\Http\Request;
use App\Filters\CustomerFilter;
use App\Http\Controllers\Controller;
use App\Http\Requests\CustomerRequest;
use App\Http\Resources\CustomerResource;
use App\Http\Requests\CustomerCreateRequest;
use App\Http\Requests\CustomerUpdateRequest;

class CustomerController extends Controller
{
    public function index(CustomerFilter $customerFilter)
    {
        $customers = Customer::filter($customerFilter)->latest()->paginate();

        return CustomerResource::collection($customers);
    }

    public function show(Customer $customer)
    {
        return response(new CustomerResource($customer));
    }

    public function update(Customer $customer, CustomerUpdateRequest $request)
    {
        $customer->update($request->except('orders'));

        // return response()->noContent();
        return new CustomerResource($customer);
    }


    public function store(CustomerCreateRequest $request)
    {

        $customer = Customer::create($request->all());

        // return response()->noContent();
        return new CustomerResource($customer);
    }

    public function destroy(Customer $customer)
    {
        $this->authorize('delete', $customer);
        $customer->delete();
        
        return response(new CustomerResource($customer));
    }
}
