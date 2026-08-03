<?php

namespace App\Http\Resources;

use App\Http\Resources\Concerns\StaffMeta;
use Illuminate\Http\Resources\Json\JsonResource;

class AttributeResource extends JsonResource
{
    use StaffMeta;

    public function toArray($request)
    {
        // Taxonómia vlastností ide aj do verejného detailu produktu.
        $staff = $this->staffUser($request);

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

            'endpoints' => $staff ? [
                'index'   => route('attributes.index'),
                'show'    => route('attributes.show', $this->id),
                'store'   => route('attributes.store'),
                'update'  => route('attributes.update', $this->id),
                'destroy' => route('attributes.destroy', $this->id),
                'values'  => route('attributes.values.index', $this->id),
            ] : [],
            'permissions' => $staff ? [
                'update' => [
                    'allowed' => $staff->can('update', $this->resource),
                    'label'   => __('actions.update'),
                ],
                'delete' => [
                    'allowed' => $staff->can('delete', $this->resource),
                    'label'   => __('actions.delete'),
                ],
            ] : [],
        ];
    }
}
