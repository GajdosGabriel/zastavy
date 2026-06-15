<?php

namespace App\Filters;

use App\Models\Product;

class StockFilter extends Filters
{
    protected $filters = ['bySearchInput', 'byProduct'];

    public function bySearchInput($input)
    {
        $productIds = Product::where('name', 'like', '%' . $input . '%')
            ->orWhere('code', 'like', '%' . $input . '%')
            ->pluck('id');

        return $this->builder->where(function ($q) use ($productIds) {
            $q->whereIn('product_id', $productIds)
              ->orWhereHas('orderProduct', fn($q) => $q->whereIn('product_id', $productIds));
        });
    }

    public function byProduct($productId)
    {
        return $this->builder->where(function ($q) use ($productId) {
            $q->where('product_id', $productId)
              ->orWhereHas('orderProduct', fn($q) => $q->where('product_id', $productId));
        });
    }
}
