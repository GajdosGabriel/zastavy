<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class OrderReturnItemResource extends JsonResource
{
    public function toArray($request)
    {
        $op = $this->whenLoaded('orderProduct');

        return [
            'id'               => $this->id,
            'order_product_id' => $this->order_product_id,
            'quantity'         => $this->quantity,
            'product_name'     => $this->when($op, fn() => $op->product->name ?? '—'),
            'product_unit'     => $this->when($op, fn() => $op->product->unit_value ?? 'ks'),
            'ordered_quantity' => $this->when($op, fn() => $op->quantity),
            'shipped_quantity' => $this->when($op, fn() => $op->stockSum),
        ];
    }
}
