<?php

namespace App\Actions;

use App\Models\Order;
use Illuminate\Http\Request;
use App\Contracts\StoreOrderContract;


class StoreOrder implements StoreOrderContract
{

    function __construct(Request $request)
    {
        $this->request = $request;
    }


    public function handle($customer)
    {
        $order = $customer->orders()->create();
        $this->serialNumber($customer, $order);
        $this->saveNotice($order);
        $this->storeOrderProducts($order);

        return $order;
    }

    protected function saveNotice($order)
    {
        new StoreNotice($order, $this->request->orderNotice);
    }


    protected function storeOrderProducts($order)
    {
        new StoreOrderProduct($order, $this->request->orderProducts);
    }

    protected function serialNumber($customer, $order)
    {
        if ($customer->ico) {

            $countOrders = Order::whereHas('customer', function ($query) use ($customer) {
                $query->whereIco($customer->ico);
            })->count();
        }

        $idPostfix = $countOrders ?? 1;
        $nextId =  STR_PAD((string)$idPostfix, 3, "0", STR_PAD_LEFT) . '/' . date("Y");
        $order->update(['serial_number' => $nextId]);
    }
}
