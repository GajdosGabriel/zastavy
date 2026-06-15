<?php

namespace App\Http\Resources;

use Carbon\Carbon;
use Illuminate\Http\Resources\Json\JsonResource;

class StockResource extends JsonResource
{
    public function toArray($request)
    {
        $user = $request->user();

        $product = $this->product;

        return [
            'id'                        => $this->id,
            'type'                      => $this->shipping_id ? 'outgoing' : 'incoming',
            'shipping_id'               => $this->shipping_id,
            'order_id'                  => $this->order_id,
            'product_id'                => $this->product_id ?? $product?->id,
            'company'                   => $this->shipping?->order?->customer?->company,
            'order_serial'              => $this->shipping?->order?->serial_number,
            'shipping_created_at_human' => $this->shipping
                ? Carbon::parse($this->shipping->created_at)->diffForHumans()
                : Carbon::parse($this->created_at)->diffForHumans(),
            'created_at_human'          => Carbon::parse($this->created_at)->diffForHumans(),
            'name'                      => $product?->name,
            'code'                      => $product?->code,
            'product_unit_value'        => $product?->unit_value,
            'quantity'                  => $this->quantity,
            'price'                     => $this->price,
            'note'                      => $this->note,
            'status'                    => $this->statusData(),
            'endpoints' => [
                'destroy' => route('stocks.destroy', $this->id),
            ],
            'permissions' => [
                'delete' => [
                    'allowed' => $user?->can('delete', $this->resource) ?? false,
                    'label'   => __('actions.delete'),
                ],
            ],
        ];
    }
}
