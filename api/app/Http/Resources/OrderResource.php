<?php

namespace App\Http\Resources;

use Carbon\Carbon;
use App\Http\Resources\NoticeResource;
use App\Http\Resources\CustomerResource;
use App\Http\Resources\ShippingResource;
use App\Http\Resources\OrderProductResource;
use Illuminate\Http\Resources\Json\JsonResource;

class OrderResource extends JsonResource
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

        return [
            'id' => $this->id,
            'isOpened' => $this->isOpened,
            'isDelivered' => $this->isDelivered,
            'serial_number' => $this->serial_number,
            'created_at' => $this->created_at->format('d.m.Y H:i:s'),
            'created_at_human' => Carbon::parse($this->created_at)->diffForhumans(),
            'customer' => new CustomerResource($this->customer),
            'user' => $this->user ? new UserResource($this->user) : null,
            'shippings' => ShippingResource::collection($this->shippings),
            'price_sum' => $this->priceSum(),
            'notices' => $this->notices ,
            'orderProducts' => OrderProductResource::collection($this->orderProducts),
            'stock_expedition' =>  $this->stockExpedition,
            'product_order_sum' => $this->productOrderSum,
            'product_storno_sum' => $this->productStornoSum(),
            'shipping_required_quantity' => $this->shippingRequiredQuantity(),
            'shipping_remaining_quantity' => $this->shippingRemainingQuantity(),
            'shipping_percentage' => $this->shippingPercentage(),
            'shipping_status_label' => $this->shippingStatusLabel(),
            'isStorned' => $this->isStorned(),
            'isFinished' => $this->isFinished(),
            'shippintPercentageCalculator' => $this->shippintPercentageCalculator(),
            'isDeleted' => $this->deleted_at != NULL,
            'mark' =>  [
                'isActive' => isset($this->mark),
                'endpoint'      =>  route('orders.marks.store', $this->id),
            ],
            'endpoints' => [
                'index'     =>  route('orders.index'),
                'show'      =>  route('orders.show', $this->id),
                'update'    =>  route('orders.update', $this->id),
                'store'     =>  route('orders.store'),
                'destroy' => route('orders.destroy', $this->id),
            ],
            'permissions' => [
                'view' => $user?->can('view', $this->resource) ?? false,
                'update' => $user?->can('update', $this->resource) ?? false,
                'storno' => $user?->can('storno', $this->resource) ?? false,
                'delete' => $user?->can('delete', $this->resource) ?? false,
                'archive' => $user?->can('archive', $this->resource) ?? false,
                'restore' => $user?->can('restore', $this->resource) ?? false,
            ],
        ];
    }
}
