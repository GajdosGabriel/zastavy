<?php

namespace App\Actions;


use App\Contracts\StoreCustomerContract;
use App\Services\CustomerService;

class StoreCustomer implements StoreCustomerContract
{

    public function handle($request)
    {
        $customer = new CustomerService();

        return  $customer->handle($request);
    }
}
