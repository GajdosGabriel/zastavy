<?php

namespace App\Services;

use App\Models\Customer;
use Request;

class CustomerService
{
  public function handle($request)
  {
    // $customer = false;

    // if (! $request['ico'] == null) {
    //   $customer = Customer::whereIco($request['ico'])->first();
    // }

    // if (!$customer) {
    //   $customer = Customer::create($request);
    // }


    $customer = Customer::create($request);

    return $customer;
  }
}
