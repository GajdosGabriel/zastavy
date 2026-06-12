<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class OrderReturnResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id'              => $this->id,
            'order_id'        => $this->order_id,
            'status'          => $this->status,
            'reason'          => $this->reason,
            'reason_label'    => $this->reason_label,
            'note'            => $this->note,
            'processed_at'    => $this->processed_at?->format('d.m.Y H:i'),
            'created_at'      => $this->created_at->format('d.m.Y H:i'),
            'created_by'      => $this->whenLoaded('createdBy', fn() => $this->createdBy?->firstName . ' ' . $this->createdBy?->lastName),
            'processed_by'    => $this->whenLoaded('processedBy', fn() => $this->processedBy?->firstName . ' ' . $this->processedBy?->lastName),
            'items'           => OrderReturnItemResource::collection($this->whenLoaded('items')),
        ];
    }
}
