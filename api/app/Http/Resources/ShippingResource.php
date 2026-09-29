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
            'prepared_items' => $this->prepared_items,
            'dispatched_at' => $this->dispatched_at?->format('d.m.Y H:i'),
            'is_preparing' => $this->dispatched_at === null,
            'status' => $this->statusData(),
            'created_at' => $this->created_at->format('d.m.Y H:i:s'),
            'quantity_sum' => $this->dispatched_at ? $this->stocks->sum('quantity') : collect($this->prepared_items)->sum('quantity'),
            'items_count' => $this->dispatched_at ? $this->stocks->count() : count($this->prepared_items ?? []),
            'stocks' => StockResource::collection($this->stocks),
            'notices' => NoticeResource::collection($this->notices),
        ];
    }
}
