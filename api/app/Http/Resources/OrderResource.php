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
            'shipping_method'   => ($this->shipping_method_name || $this->shippingMethod) ? ['id' => $this->shipping_method_id, 'name' => $this->shipping_method_name ?? $this->shippingMethod?->name] : null,
            'payment_method'    => ($this->payment_method_name || $this->paymentMethod) ? ['id' => $this->payment_method_id,  'name' => $this->payment_method_name ?? $this->paymentMethod?->name]  : null,
            'uuid' => $this->uuid,
            'isOpened' => $this->isOpened,
            'serial_number' => $this->serial_number,
            'name' => $this->name,
            'email' => $this->email,
            'phone' => $this->phone,
            'created_at' => $this->created_at->format('d.m.Y H:i:s'),
            'created_at_human' => Carbon::parse($this->created_at)->diffForhumans(),
            'customer' => array_replace((new CustomerResource($this->customer))->resolve($request), $this->billingSnapshot()),
            'snapshot_source' => $this->snapshot_source ?? 'legacy',

            // Doručovacia adresa objednávky. `is_custom = false` znamená, že sa
            // doručuje na sídlo zákazníka — hodnoty sú vtedy jeho.
            'delivery' => $this->deliverySnapshot(),
            'customer_address_id' => $this->customer_address_id,
            'can_edit_delivery' => $this->canEditDelivery(),
            'delivery_changed_at' => $this->delivery_changed_at?->format('d.m.Y H:i'),
            'delivery_changed_by' => $this->delivery_changed_by,
            'delivery_edit_url' => $this->deliveryEditUrl(),
            'user' => $this->user ? new UserResource($this->user) : null,
            'shippings' => ShippingResource::collection($this->shippings),
            'price_sum' => $this->priceSum(),
            'note'         => $this->note,
            'wants_coupon' => (bool) $this->wants_coupon,
            'issued_coupon' => $this->wants_coupon && $this->issuedCoupon ? [
                'code'        => $this->issuedCoupon->code,
                'valid_from'  => $this->issuedCoupon->valid_from?->format('d.m.Y'),
                'valid_to'    => $this->issuedCoupon->valid_to?->format('d.m.Y'),
                'used_count'  => (int) $this->issuedCoupon->used_count,
                'usage_limit' => $this->issuedCoupon->usage_limit,
                'created_at'  => $this->issuedCoupon->created_at?->format('d.m.Y'),
            ] : null,
            'orderProducts' => OrderProductResource::collection($this->orderProducts),
            'attachments' => AttachmentResource::collection($this->whenLoaded('attachments')),
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
                'ship' => ['allowed' => $user?->can('ship', $this->resource) ?? false],
                'manageReturns' => ['allowed' => $user?->can('manageReturns', $this->resource) ?? false],
                'manageItems' => ['allowed' => $user?->can('manageItems', $this->resource) ?? false],
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
