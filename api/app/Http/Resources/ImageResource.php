<?php

namespace App\Http\Resources;

use Illuminate\Support\Facades\Storage;
use Illuminate\Http\Resources\Json\JsonResource;

class ImageResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array|\Illuminate\Contracts\Support\Arrayable|\JsonSerializable
     */
    public function toArray($request)
    {
        $path = preg_replace('#^public/#', '', $this->path);

        return [
            'id' => $this->id,
            'status' => $this->statusData(),
            'path' => Storage::disk('public')->url($path),
        ];
    }
}
