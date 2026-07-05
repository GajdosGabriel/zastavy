<?php

namespace App\Actions;


use App\Contracts\StoreOrderProductContract;


class StoreOrderProduct implements StoreOrderProductContract
{
    /**
     * @param  \Illuminate\Support\Collection|array  $items  Položky s kľúčmi product_id, quantity, price (serverová cena).
     */
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
                'product_id' => $value['product_id'],
                'quantity' => $value['quantity'],
                'price' => $value['price'],
                'total' => $value['quantity'] * $value['price']
            ]);
        }
    }
}
