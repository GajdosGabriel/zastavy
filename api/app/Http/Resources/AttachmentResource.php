<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class AttachmentResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'mime' => $this->mime,
            'size' => $this->size,
            'is_image' => $this->isImage(),
            'created_at' => $this->created_at?->format('d.m.Y H:i'),
            // Prílohy sú súkromné — sťahujú sa cez API, nie priamym odkazom do bucketu.
            'download' => $this->when(
                $this->attachable_type === \App\Models\Order::class,
                fn () => route('orders.attachments.show', [$this->attachable_id, $this->id])
            ),
        ];
    }
}
