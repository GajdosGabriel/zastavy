<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class CategoryResource extends JsonResource
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
            'name' => $this->name,
            'slug' => $this->slug,
            'url' => [
                'index'     =>  route('categories.index'),
                'show'      =>  route('categories.show', $this->id),
                'update'    =>  route('categories.update', $this->id),
                'store'     =>  route('categories.store'),
                'destroy' => $this->when($request->user()->can("delete", $this->resource), route('categories.destroy', $this->id))
            ],
        ];
    }
}
