<?php

namespace App\Actions;

use App\Services\CustomerService;
use App\Contracts\StoreCheckoutContract;
use Illuminate\Http\Request;

class StoreCheckout implements StoreCheckoutContract
{
    function __construct(Request $request)
    {
        $this->request = $request;
        $this->handle();
    }

    public function handle()
    {

        if (!count($this->request->orderProducts)) {
            return 'Prázdna objednávka';
        }

        $this->getCustomer();
    }

    public function getCustomer()
    {

        $customerService = new CustomerService();
        $customer = $customerService->handle($this->request->customer);

        $this->createOrder($customer);
    }

    public function createOrder($customer)
    {
        $storeOrder = new StoreOrder($this->request);

        $storeOrder->handle($customer);
    }
}
