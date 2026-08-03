<?php

namespace App\Http\Resources;

use App\Http\Resources\Concerns\StaffMeta;
use Illuminate\Http\Resources\Json\JsonResource;

class CategoryResource extends JsonResource
{
    use StaffMeta;

    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array|\Illuminate\Contracts\Support\Arrayable|\JsonSerializable
     */
    public function toArray($request)
    {
        // ProductResource posiela kategórie aj na verejných endpointoch — tam sa
        // admin routes vynechajú úplne.
        $staff = $this->staffUser($request);

        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'status' => $this->statusData(),
            'url' => $staff ? [
                'index'     =>  route('categories.index'),
                'show'      =>  route('categories.show', $this->id),
                'update'    =>  route('categories.update', $this->id),
                'store'     =>  route('categories.store'),
                'destroy'   =>  $this->when($staff->can('delete', $this->resource), route('categories.destroy', $this->id)),
            ] : [],
        ];
    }
}
