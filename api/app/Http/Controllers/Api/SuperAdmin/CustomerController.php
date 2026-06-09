<?php

namespace App\Http\Controllers\Api\SuperAdmin;

use App\Enums\ModelStatus;
use App\Models\Customer;
use App\Filters\CustomerFilter;
use App\Http\Controllers\Controller;
use App\Http\Requests\CustomerCreateRequest;
use App\Http\Requests\CustomerUpdateRequest;
use App\Http\Resources\CustomerResource;
use App\Services\CustomerService;
use Illuminate\Support\Facades\Gate;

class CustomerController extends Controller
{
    public function index(CustomerFilter $customerFilter)
    {
        Gate::authorize('viewAny', Customer::class);

        $customers = Customer::with('users')->filter($customerFilter)->latest()->paginate();

        return CustomerResource::collection($customers);
    }

    public function show(Customer $customer)
    {
        Gate::authorize('view', $customer);

        return (new CustomerResource($customer->load('users')))
            ->additional($this->formOptions());
    }

    public function store(CustomerCreateRequest $request)
    {
        Gate::authorize('create', Customer::class);

        [$customer] = (new CustomerService)->handleCheckout($request->all());

        return new CustomerResource($customer->load('users'));
    }

    public function update(Customer $customer, CustomerUpdateRequest $request)
    {
        Gate::authorize('update', $customer);

        (new CustomerService)->updateWithUser($customer, $request->except('orders'));

        return (new CustomerResource($customer->refresh()->load('users')))
            ->additional($this->formOptions());
    }

    public function destroy(Customer $customer)
    {
        Gate::authorize('delete', $customer);

        $customer->delete();

        return response(new CustomerResource($customer));
    }

    private function formOptions(): array
    {
        return [
            'meta' => [
                'statuses' => ModelStatus::allowedForUser(request()->user()),
            ],
        ];
    }
}
