<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

class OrderProductResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array|\Illuminate\Contracts\Support\Arrayable|\JsonSerializable
     */
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'product_id' => $this->product_id,
            'order_id' => $this->order_id,
            'name' => $this->product->name,
            'activePrice' => $this->product->activePrice,
            'thumb' => url($this->product->thumb),
            'unit_value' => $this->product->unit_value,
            'min_order' => $this->product->min_order,
            'product_vat' => $this->product->vat,
            'quantity' => $this->quantity,
            'status' => $this->statusData(),
            'sent_at' => $this->sent_at,
            'order_id' => $this->order_id,
            'price' => $this->price,
            'total' => $this->total,
            'storno' => $this->storno,
            'stockSum' => $this->stockSum,
            'shipping_remaining_quantity' => max(0, $this->quantity - $this->storno - $this->stockSum),
            'shipping_required_quantity' => max(0, $this->quantity - $this->storno),
            'shipping_percentage' => max(0, $this->quantity - $this->storno) > 0
                ? round(min(100, ($this->stockSum / max(1, $this->quantity - $this->storno)) * 100), 1)
                : 0,

            'endpoints'   => [
                // 'index'     =>  route('orders.posts.index', [auth()->user()->active_organization]),
                // 'show'      =>  route('orders.orderProduct.show', [auth()->user()->active_organization, $this->id]),
                'update'    =>  route('orders.orderProducts.update', [$this->order_id, $this->id]),
                'store'     =>  route('orders.orderProducts.store', [$this->order_id]),
                'destroy'   =>  route('orders.orderProducts.destroy', [$this->order_id, $this->id]),
            ],
        ];
    }
}
