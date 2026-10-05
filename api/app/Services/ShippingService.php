<?php

namespace App\Services;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\ProductVariant;
use App\Models\Stock;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ShippingService
{
    public function create($order, ?array $items = null, ?string $key = null, ?\App\Models\Shipping $prepared = null)
    {
        return DB::transaction(function () use ($order, $items, $key, $prepared) {
            $order = Order::whereKey($order->id)->lockForUpdate()->firstOrFail();
            if ($prepared) {
                $prepared = $order->shippings()->whereKey($prepared->id)->lockForUpdate()->firstOrFail();
                if ($prepared->dispatched_at) return $prepared;
            }
            $hash = hash('sha256', json_encode($items));
            if ($key && ($existing = $order->shippings()->where('submission_key', $key)->first())) {
                abort_unless(hash_equals($existing->submission_hash, $hash), 409, 'Kľúč expedície už patrí inému obsahu.');

                return $existing;
            }
            abort_if(in_array($order->status, [OrderStatus::Cancelled, OrderStatus::Archived]), 422, 'Túto objednávku nemožno expedovať.');
            $order->load('orderProducts.stocks');
            foreach ($items ?? [] as $selection) {
                abort_unless($order->orderProducts->contains('id', $selection['order_product_id']), 422, 'Položka nepatrí k objednávke.');
            }
            $variants = ProductVariant::withTrashed()
                ->whereIn('id', $order->orderProducts->pluck('product_variant_id')->filter())
                ->orderBy('id')->lockForUpdate()->get()->keyBy('id');
            $order->update(['isOpened' => 1]);
            $itemsToShip = $items === null ? null : collect($items)
                ->keyBy('order_product_id')
                ->map(fn ($item) => (int) $item['quantity']);

            $stocks = collect();

            foreach ($order->orderProducts as $item) {
                $alreadyShipped = $item->relationLoaded('stocks')
                  ? (int) $item->stocks->sum('quantity')
                  : $this->sumShippingItems($item);
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

                if ($prepared && $quantity !== (int) $itemsToShip->get($item->id, 0)) {
                    throw ValidationException::withMessages(['items' => 'Položky sa zmenili. Zrušte prípravu a vytvorte nový dodací list.']);
                }
                $variant = $variants->get($item->product_variant_id);
                if ($variant && $variant->quantity !== null && ! $variant->product?->made_to_order) {
                    if ((int) $variant->quantity < $quantity) {
                        throw ValidationException::withMessages(['items' => 'Nedostatok zásoby pre '.$variant->code.'. Dostupné: '.$variant->quantity]);
                    }
                    // Viac riadkov tej istej varianty zdieľa tú istú zásobu.
                    $variant->quantity -= $quantity;
                }
                $stocks->push(new Stock([
                    'order_id' => $order->id,
                    'order_product_id' => $item->id,
                    'quantity' => $quantity,
                    'inventory_delta' => $item->is_custom ? 0 : null,
                ]));
            }

            $shipping = null;

            if ($stocks->isNotEmpty()) {
                $shipping = $prepared ?? $order->shippings()->create(['submission_key' => $key, 'submission_hash' => $key ? $hash : null]);
                $shipping->update(['dispatched_at' => now()]);
                $shipping->stocks()->saveMany($stocks);
                \App\Services\SystemLog\Activity::record('order', 'dispatched', 'Zásielka expedovaná #'.($order->serial_number ?: $order->id),
                    ['shipping_id' => $shipping->id, 'items' => $stocks->map(fn (Stock $stock) => [
                        'order_product_id' => $stock->order_product_id, 'quantity' => $stock->quantity,
                    ])->all()], $order);
            }

            return $shipping;
        }, 3);
    }

    protected function sumShippingItems($item)
    {
        return Stock::where('order_product_id', '=', $item->id)->get()->sum('quantity');
    }
}
