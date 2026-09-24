<?php

namespace App\Services;

use App\Models\Order;
use App\Models\ProductVariant;

class ReorderService
{
    public function preview(Order $order): array
    {
        $items = $order->orderProducts;
        $variants = ProductVariant::with(['product.images', 'image'])->whereIn('id', $items->pluck('product_variant_id'))->get()->keyBy('id');

        return $items->map(function ($item) use ($variants) {
            $variant = $variants->get($item->product_variant_id);
            $product = $variant?->product;
            $available = $variant && $product && $variant->published && $product->published;
            $quantity = max((int) $item->quantity, (int) ($variant?->min_order ?? 1));

            return ['name' => $item->product_details->name ?? 'Produkt', 'variant_name' => $item->variant_name, 'available' => (bool) $available,
                'old_price' => (float) $item->price, 'old_quantity' => (int) $item->quantity,
                'price_changed' => $available && (float) $variant->active_price !== (float) $item->price,
                'quantity_changed' => $available && $quantity !== (int) $item->quantity,
                'cart' => $available ? ['product_id' => $product->id, 'variant_id' => $variant->id, 'name' => $product->name, 'variant_name' => $variant->name, 'slug' => $product->slug,
                    'thumb' => $variant->thumb, 'unit_value' => $product->unit_value, 'vat' => $product->vat, 'active_price' => (float) $variant->active_price, 'min_order' => max(1, (int) $variant->min_order), 'input_order' => $quantity] : null];
        })->values()->all();
    }
}
