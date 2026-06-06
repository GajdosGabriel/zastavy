<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class AnnouncementResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'placement' => $this->placement,
            'title' => $this->title,
            'body' => $this->body,
            'style_class' => $this->style_class,
            'sort_order' => $this->sort_order,
            'published_from' => $this->published_from?->format('Y-m-d\TH:i'),
            'published_until' => $this->published_until?->format('Y-m-d\TH:i'),
            'status' => $this->statusData(),
            'created_at' => $this->created_at?->format('d.m.Y H:i:s'),
            'updated_at' => $this->updated_at?->format('d.m.Y H:i:s'),
            'endpoints' => [
                'index' => route('announcements.index'),
                'show' => route('announcements.show', $this->id),
                'update' => route('announcements.update', $this->id),
                'store' => route('announcements.store'),
                'destroy' => route('announcements.destroy', $this->id),
            ],
        ];
    }
}
