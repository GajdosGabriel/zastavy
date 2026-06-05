<?php

namespace App\Http\Resources;

use Carbon\Carbon;
use Illuminate\Http\Resources\Json\JsonResource;

class NoticeResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array|\Illuminate\Contracts\Support\Arrayable|\JsonSerializable
     */
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'notice' => $this->notice,
            'created_at' => Carbon::parse($this->created_at)->format('d-m-Y'),
            'created_at_human' => Carbon::parse($this->created_at)->diffForhumans(),
        ];
    }
}
