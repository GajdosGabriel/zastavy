<?php

namespace App\Http\Resources;

use App\Enums\OrderStatus;
use Carbon\Carbon;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OrderResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  Request  $request
     * @return array|Arrayable|\JsonSerializable
     */
    public function toArray($request)
    {
        $user = $request->user();
        $status = OrderStatus::fromOrder($this->resource);

        return [
            'id'                => $this->id,
            'shipping_method'   => $this->shippingMethod ? ['id' => $this->shippingMethod->id, 'name' => $this->shippingMethod->name] : null,
            'payment_method'    => $this->paymentMethod  ? ['id' => $this->paymentMethod->id,  'name' => $this->paymentMethod->name]  : null,
            'uuid' => $this->uuid,
            'isOpened' => $this->isOpened,
            'serial_number' => $this->serial_number,
            'name' => $this->name,
            'email' => $this->email,
            'phone' => $this->phone,
            'created_at' => $this->created_at->format('d.m.Y H:i:s'),
            'created_at_human' => Carbon::parse($this->created_at)->diffForhumans(),
            'customer' => new CustomerResource($this->customer),
            'user' => $this->user ? new UserResource($this->user) : null,
            'shippings' => ShippingResource::collection($this->shippings),
            'price_sum' => $this->priceSum(),
            'note'         => $this->note,
            'wants_coupon' => (bool) $this->wants_coupon,
            'orderProducts' => OrderProductResource::collection($this->orderProducts),
            'stock_expedition' => $this->stockExpedition,
            'product_order_sum' => $this->productOrderSum,
            'product_storno_sum' => $this->productStornoSum(),
            'shipping_required_quantity' => $this->shippingRequiredQuantity(),
            'shipping_remaining_quantity' => $this->shippingRemainingQuantity(),
            'shipping_percentage' => $this->shippingPercentage(),
            'shipping_status_label' => $this->shippingStatusLabel(),
            'status' => $status->toArray(),
            'isStorned' => $this->isStorned(),
            'isFinished' => $this->isFinished(),
            'shippintPercentageCalculator' => $this->shippintPercentageCalculator(),
            'isDeleted' => $this->deleted_at != null,
            'mark' => [
                'isActive' => isset($this->mark),
                'endpoint' => route('orders.marks.store', $this->id),
            ],
            'endpoints' => [
                'index' => route('orders.index'),
                'show' => route('orders.show', $this->id),
                'update' => route('orders.update', $this->id),
                'store' => route('orders.store'),
                'destroy' => route('orders.destroy', $this->id),
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
                'storno' => [
                    'allowed' => $user?->can('storno', $this->resource) ?? false,
                    'label' => $this->isStorned() ? __('actions.cancel_storno') : __('actions.storno'),
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
