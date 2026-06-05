<?php

namespace App\Actions;


use Illuminate\Support\Facades\Request;
use App\Contracts\StoreOrderProductContract;


class StoreOrderProduct implements StoreOrderProductContract
{
    function __construct($order, $items)
    {
        $this->items = $items;
        $this->order = $order;
        $this->handle();
    }


    public function handle()
    {
        foreach ($this->items as $value) {

            $this->order->orderProducts()->create([
                'product_id' => $value['id'],
                'quantity' => $value['input_order'],
                'price' => $value['active_price'],
                'total' => $value['input_order'] * $value['active_price']
            ]);
        }
    }
}
