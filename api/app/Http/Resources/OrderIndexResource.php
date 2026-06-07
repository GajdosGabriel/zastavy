<?php

namespace App\Http\Resources;

use App\Enums\ModelStatus;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OrderIndexResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $user = $request->user();
        $status = ModelStatus::fromOrder($this->resource);
        $orderedQuantity = (int) $this->orderProducts->sum('quantity');
        $stornoQuantity = (int) $this->orderProducts->sum('storno');
        $requiredQuantity = max(0, $orderedQuantity - $stornoQuantity);
        $stockExpedition = (int) $this->stocks->sum('quantity');
        $isStorned = $this->status === ModelStatus::Cancelled
            || ($orderedQuantity > 0 && $orderedQuantity === $stornoQuantity);
        $isFinished = ! $isStorned && $requiredQuantity === $stockExpedition;
        $shippingPercentage = $requiredQuantity === 0
            ? 0
            : round(min(100, ($stockExpedition / $requiredQuantity) * 100), 1);

        return [
            'id' => $this->id,
            'uuid' => $this->uuid,
            'isOpened' => $this->isOpened,
            'isDelivered' => $this->isDelivered,
            'serial_number' => $this->serial_number,
            'created_at' => $this->created_at->format('d.m.Y H:i:s'),
            'created_at_human' => Carbon::parse($this->created_at)->diffForhumans(),
            'customer' => [
                'id' => $this->customer?->id,
                'name' => $this->customer?->name,
                'company' => $this->customer?->company,
                'city' => $this->customer?->city,
            ],
            'shippings' => ShippingResource::collection($this->shippings),
            'price_sum' => $this->orderProducts->sum('total'),
            'notices' => NoticeResource::collection($this->notices),
            'orderProducts' => OrderProductResource::collection($this->orderProducts),
            'stock_expedition' => $stockExpedition,
            'product_order_sum' => $orderedQuantity,
            'product_storno_sum' => $stornoQuantity,
            'shipping_required_quantity' => $requiredQuantity,
            'shipping_remaining_quantity' => max(0, $requiredQuantity - $stockExpedition),
            'shipping_percentage' => $shippingPercentage,
            'shipping_status_label' => $this->shippingStatusLabel($isStorned, $isFinished, $requiredQuantity, $stockExpedition),
            'status' => $status->toArray(),
            'isStorned' => $isStorned,
            'isFinished' => $isFinished,
            'shippintPercentageCalculator' => $requiredQuantity === 0 ? 'Prázdna objednávka' : $shippingPercentage.'%',
            'isDeleted' => $this->deleted_at != null,
            'mark' => [
                'isActive' => $this->mark !== null,
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
                'view' => $user?->can('view', $this->resource) ?? false,
                'update' => $user?->can('update', $this->resource) ?? false,
                'storno' => $user?->can('storno', $this->resource) ?? false,
                'delete' => $user?->can('delete', $this->resource) ?? false,
                'archive' => $user?->can('archive', $this->resource) ?? false,
                'restore' => $user?->can('restore', $this->resource) ?? false,
            ],
        ];
    }

    private function shippingStatusLabel(bool $isStorned, bool $isFinished, int $requiredQuantity, int $stockExpedition): string
    {
        if ($isStorned) {
            return 'Stornovaná';
        }

        if ($requiredQuantity === 0) {
            return 'Prázdna';
        }

        if ($isFinished) {
            return 'Vybavená';
        }

        if ($stockExpedition > 0) {
            return 'Čiastočne vybavená';
        }

        return 'Nevybavená';
    }
}
