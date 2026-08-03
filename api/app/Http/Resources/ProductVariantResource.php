<?php

namespace App\Http\Resources;

use App\Enums\ModelStatus;
use App\Http\Resources\Concerns\StaffMeta;
use Illuminate\Http\Resources\Json\JsonResource;

class ProductVariantResource extends JsonResource
{
    use StaffMeta;

    public function toArray($request)
    {
        $staff = $this->staffUser($request);

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

            // Varianty visia na verejnom detaile produktu — admin routes len personálu.
            'endpoints' => $staff ? [
                'index'   => route('products.variants.index', $this->product_id),
                'show'    => route('products.variants.show', [$this->product_id, $this->id]),
                'store'   => route('products.variants.store', $this->product_id),
                'update'  => route('products.variants.update', [$this->product_id, $this->id]),
                'destroy' => route('products.variants.destroy', [$this->product_id, $this->id]),
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
