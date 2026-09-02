<?php

namespace App\Filters;

use App\Models\Product;
use App\Models\ProductVariant;

class StockFilter extends Filters
{
    protected $filters = ['bySearchInput', 'byProduct', 'byVariant', 'byType'];

    /**
     * Hľadá aj podľa kódu a názvu variantu — tabuľka zobrazuje práve ten,
     * takže hľadanie musí nájsť to, čo je na obrazovke.
     */
    public function bySearchInput($input)
    {
        $productIds = Product::where('name', 'like', '%' . $input . '%')
            ->orWhere('code', 'like', '%' . $input . '%')
            ->pluck('id');

        $variantIds = ProductVariant::where('code', 'like', '%' . $input . '%')
            ->orWhere('name', 'like', '%' . $input . '%')
            ->pluck('id');

        return $this->builder->where(function ($q) use ($productIds, $variantIds) {
            $q->whereIn('product_id', $productIds)
              ->orWhereIn('product_variant_id', $variantIds)
              ->orWhereHas('orderProduct', fn ($q) => $q
                  ->whereIn('product_id', $productIds)
                  ->orWhereIn('product_variant_id', $variantIds));
        });
    }

    public function byProduct($productId)
    {
        return $this->builder->where(function ($q) use ($productId) {
            $q->where('product_id', $productId)
              ->orWhereHas('orderProduct', fn($q) => $q->where('product_id', $productId));
        });
    }

    /**
     * Príjem má variant priamo, výdaj cez položku objednávky.
     */
    public function byVariant($variantId)
    {
        return $this->builder->where(function ($q) use ($variantId) {
            $q->where('product_variant_id', $variantId)
              ->orWhereHas('orderProduct', fn($q) => $q->where('product_variant_id', $variantId));
        });
    }

    /**
     * incoming = príjem, writeoff = odpis (záporný príjem), outgoing = expedícia.
     */
    public function byType($type)
    {
        return match ($type) {
            'incoming' => $this->builder->whereNull('shipping_id')->where('quantity', '>', 0),
            'writeoff' => $this->builder->whereNull('shipping_id')->where('quantity', '<', 0),
            'outgoing' => $this->builder->whereNotNull('shipping_id'),
            default    => $this->builder,
        };
    }
}
