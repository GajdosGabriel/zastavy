<?php

namespace App\Http\Controllers\Api;


use App\Models\Customer;
use Illuminate\Http\Request;
use App\Actions\StoreCheckout;
use App\Http\Requests\OrderRequest;
use App\Http\Controllers\Controller;
use App\Http\Resources\CustomerResource;


class CheckoutController extends Controller
{

    public function show($ico)
    {
        return new CustomerResource(Customer::whereIco($ico)->first());
    }

    public function store(OrderRequest $request)
    {

        new StoreCheckout($request);
    }
}
