<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class AttributeResource extends JsonResource
{
    public function toArray($request)
    {
        $user = $request->user();

        return [
            'id'            => $this->id,
            'code'          => $this->code,
            'name'          => $this->name,
            'unit'          => $this->unit,
            'input_type'    => $this->input_type,
            'is_variant'    => $this->is_variant,
            'is_filterable' => $this->is_filterable,
            'is_public'     => $this->is_public,
            'sort_order'    => $this->sort_order,
            'status'        => $this->statusData(),
            'values'        => AttributeValueResource::collection($this->whenLoaded('values')),
            'values_count'  => $this->whenCounted('values'),

            'endpoints' => [
                'index'   => route('attributes.index'),
                'show'    => route('attributes.show', $this->id),
                'store'   => route('attributes.store'),
                'update'  => route('attributes.update', $this->id),
                'destroy' => route('attributes.destroy', $this->id),
                'values'  => route('attributes.values.index', $this->id),
            ],
            'permissions' => [
                'update' => [
                    'allowed' => $user?->can('update', $this->resource) ?? false,
                    'label'   => __('actions.update'),
                ],
                'delete' => [
                    'allowed' => $user?->can('delete', $this->resource) ?? false,
                    'label'   => __('actions.delete'),
                ],
            ],
        ];
    }
}
