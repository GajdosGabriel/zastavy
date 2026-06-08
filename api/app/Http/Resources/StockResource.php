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
        $user = $request->user();

        return[
            'id' => $this->id,
            'shipping_id' => $this->shipping->id,
            'order_id' => $this->order_id,
            'company' => $this->shipping->order->customer->company,
            'shipping_created_at_human' => Carbon::parse($this->shipping->created_at)->diffForhumans(),
            'name' => $this->product->name,
            'product_unit_value' => $this->product->unit_value,
            'quantity' => $this->quantity,
            'status' => $this->statusData(),
            'endpoints' => [
                'destroy' => route('stocks.destroy', $this->id),
            ],
            'permissions' => [
                'view' => [
                    'allowed' => $user?->can('view', $this->resource) ?? false,
                    'label' => __('actions.view'),
                ],
                'update' => [
                    'allowed' => $user?->can('update', $this->resource) ?? false,
                    'label' => __('actions.update'),
                ],
                'delete' => [
                    'allowed' => $user?->can('delete', $this->resource) ?? false,
                    'label' => __('actions.delete'),
                ],
                'archive' => [
                    'allowed' => $user?->can('archive', $this->resource) ?? false,
                    'label' => __('actions.archive'),
                ],
                'restore' => [
                    'allowed' => $user?->can('restore', $this->resource) ?? false,
                    'label' => __('actions.restore'),
                ],
            ],
        ];
    }
}
