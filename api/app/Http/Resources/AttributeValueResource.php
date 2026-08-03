<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class AttributeValueResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id'           => $this->id,
            'attribute_id' => $this->attribute_id,
            'code'         => $this->code,
            'value'        => $this->value,
            'slug'         => $this->slug,
            'color'        => $this->color,
            'sort_order'   => $this->sort_order,
            'facet_slug'   => $this->whenLoaded('attribute', fn () => $this->facet_slug),
            'attribute'    => $this->whenLoaded('attribute', fn () => [
                'id'   => $this->attribute->id,
                'code' => $this->attribute->code,
                'name' => $this->attribute->name,
                'unit' => $this->attribute->unit,
            ]),
        ];
    }
}
