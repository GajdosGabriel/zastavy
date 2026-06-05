<?php

namespace App\Services;

use App\Models\Stock;
use App\Notifications\OrderExpedition;


class ShippingService
{
  public function create($order)
  {

    $stocks = collect();

    foreach ($order->orderProducts as $item) {
      $this->sumShippingItems($item);

      if ($this->sumShippingItems($item) === $item->quantity) {
        continue;
      };

      $stocks->push(new Stock([
        'order_id' => $order->id,
        'order_product_id' => $item->id,
        'quantity' => $item->quantity - $item->storno - $this->sumShippingItems($item) // Rozdiel v počte dodaných kusov
      ]));
    }

    if ($stocks->isNotEmpty()) {
      $shipping = $order->shippings()->create();
      $shipping->stocks()->saveMany($stocks);
    }

    return $shipping;
  }


  protected function sumShippingItems($item)
  {
    return Stock::where('order_product_id', '=', $item->id)->get()->sum('quantity');
  }
}
