<?php

namespace App\Actions;

use App\Models\Order;
use App\Services\CustomerService;
use App\Contracts\StoreCheckoutContract;
use Illuminate\Http\Request;

class StoreCheckout implements StoreCheckoutContract
{
    private Order $order;

    public function __construct(private Request $request)
    {
        $this->handle();
    }

    public function handle(): void
    {
        $this->getCustomer();
    }

    public function getCustomer(): void
    {
        $customerService = new CustomerService();
        [$customer, $user] = $customerService->handleCheckout($this->request->customer);
        $this->createOrder($customer, $user);
    }

    public function createOrder($customer, $user = null): void
    {
        $storeOrder = new StoreOrder($this->request);
        $this->order = $storeOrder->handle($customer, $user);
    }

    public function getOrder(): Order
    {
        return $this->order;
    }
}
