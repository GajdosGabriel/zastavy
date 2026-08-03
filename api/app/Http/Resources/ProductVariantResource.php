<?php

namespace App\Http\Resources;

use App\Enums\ModelStatus;
use Illuminate\Http\Resources\Json\JsonResource;

class ProductVariantResource extends JsonResource
{
    public function toArray($request)
    {
        $user = $request->user();

        return [
            'id'          => $this->id,
            'product_id'  => $this->product_id,
            'code'        => $this->code,
            'ean'         => $this->ean,
            'name'        => $this->name,
            'price'       => $this->price,
            'sale_price'  => $this->sale_price,
            'discount'    => $this->discount,
            'active_price' => $this->active_price,
            'quantity'    => $this->quantity,
            'weight'      => $this->weight,
            'min_order'   => $this->min_order,
            'is_default'  => $this->is_default,
            'published'   => $this->published,
            'sort_order'  => $this->sort_order,
            'is_in_stock' => $this->is_in_stock,
            'status'      => ModelStatus::fromVariant($this->resource)->toArray(),
            'thumb'       => $this->thumb,

            'attribute_values' => AttributeValueResource::collection($this->whenLoaded('attributeValues')),

            'endpoints' => [
                'index'   => route('products.variants.index', $this->product_id),
                'show'    => route('products.variants.show', [$this->product_id, $this->id]),
                'store'   => route('products.variants.store', $this->product_id),
                'update'  => route('products.variants.update', [$this->product_id, $this->id]),
                'destroy' => route('products.variants.destroy', [$this->product_id, $this->id]),
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
