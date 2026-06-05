<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class ShippingResource extends JsonResource
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
            'order_id' => $this->order_id,
            'created_at' => $this->created_at->format('d.m.Y H:i:s'),
            'quantity_sum' => $this->stocks->sum('quantity'),
            'items_count' => $this->stocks->count(),
            'stocks' => StockResource::collection($this->stocks),
            'notices' => NoticeResource::collection($this->notices),
        ];
    }
}
