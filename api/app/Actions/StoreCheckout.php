<?php

namespace App\Actions;

use App\Services\CustomerService;
use App\Contracts\StoreCheckoutContract;
use Illuminate\Http\Request;

class StoreCheckout implements StoreCheckoutContract
{
    public function __construct(private Request $request)
    {
        $this->handle();
    }

    public function handle()
    {
        $this->getCustomer();
    }

    public function getCustomer()
    {

        $customerService = new CustomerService();
        [$customer, $user] = $customerService->handleCheckout($this->request->customer);

        $this->createOrder($customer, $user);
    }

    public function createOrder($customer, $user = null)
    {
        $storeOrder = new StoreOrder($this->request);

        $storeOrder->handle($customer, $user);
    }
}
