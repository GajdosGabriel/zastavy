<?php

namespace App\Http\Resources;

use Carbon\Carbon;
use Illuminate\Http\Resources\Json\JsonResource;

class StockResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array|\Illuminate\Contracts\Support\Arrayable|\JsonSerializable
     */
    public function toArray($request)
    {
        return[
            'id' => $this->id,
            'shipping_id' => $this->shipping->id,
            'order_id' => $this->order_id,
            'company' => $this->shipping->order->customer->company,
            'shipping_created_at_human' => Carbon::parse($this->shipping->created_at)->diffForhumans(),
            'name' => $this->product->name,
            'product_unit_value' => $this->product->unit_value,
            'quantity' => $this->quantity,
        ];
    }
}
