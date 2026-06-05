<?php

namespace App\Contracts;

use App\Services\CustomerService;

interface StoreOrderContract
{
    public function handle($customer);
}
