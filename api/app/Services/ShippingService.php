<?php

namespace App\Services;

use App\Enums\ModelStatus;
use App\Models\Stock;
use Illuminate\Support\Collection;

class ShippingService
{
  public function create($order, ?array $items = null)
  {
    $itemsToShip = $items === null ? null : collect($items)
      ->keyBy('order_product_id')
      ->map(fn ($item) => (int) $item['quantity']);

    $stocks = collect();

    foreach ($order->orderProducts as $item) {
      $alreadyShipped = $this->sumShippingItems($item);
      $remaining = max(0, $item->quantity - $item->storno - $alreadyShipped);

      if ($remaining === 0) {
        continue;
      }

      $quantity = $itemsToShip instanceof Collection
        ? min($remaining, max(0, (int) ($itemsToShip->get($item->id, 0))))
        : $remaining;

      if ($quantity === 0) {
        continue;
      }

      $stocks->push(new Stock([
        'order_id' => $order->id,
        'order_product_id' => $item->id,
        'quantity' => $quantity,
      ]));
    }

    $shipping = null;

    if ($stocks->isNotEmpty()) {
      $shipping = $order->shippings()->create();
      $shipping->stocks()->saveMany($stocks);
      $order->update(['status' => ModelStatus::Archived]);
    }

    return $shipping;
  }

  protected function sumShippingItems($item)
  {
    return Stock::where('order_product_id', '=', $item->id)->get()->sum('quantity');
  }
}
