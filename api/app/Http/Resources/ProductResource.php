<?php

namespace App\Http\Resources;

use App\Enums\ModelStatus;
use Illuminate\Support\Facades\Storage;
use Illuminate\Http\Resources\Json\JsonResource;

class ProductResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array|\Illuminate\Contracts\Support\Arrayable|\JsonSerializable
     */
    public function toArray($request)
    {
        $user = $request->user();
        $status = ModelStatus::fromProduct($this->resource);

        return [
            'id' => $this->id,
            'code' => $this->code,
            'name' => $this->name,
            'slug' => $this->slug,
            'description' => $this->description,
            'published' => $this->published,
            'status' => $status->toArray(),
            'status_options' => ModelStatus::allowedForUser($user),
            'vat' => $this->vat,
            'thumb' => url($this->thumb),
            'unit_value' => $this->unit_value,
            // lastmod pre sitemap.xml (ui/scripts/generate-sitemap.mjs).
            'updated_at' => $this->updated_at?->toIso8601String(),

            // Cena a sklad žijú na variantoch — produkt ukazuje len rozsah.
            'price_from' => $this->price_from,
            'price_to' => $this->price_to,
            'total_quantity' => $this->total_quantity,
            'is_in_stock' => $this->is_in_stock,
            'variants_count' => $this->variants->count(),
            'variants' => ProductVariantResource::collection(
                $this->whenLoaded('variants')
            ),
            'default_variant' => new ProductVariantResource(
                $this->whenLoaded('defaultVariant')
            ),
            'attributes_taxonomy' => AttributeResource::collection(
                $this->whenLoaded('attributesTaxonomy')
            ),

            'images' => ImageResource::collection($this->images),
            'categories' => CategoryResource::collection($this->categories),

            'endpoints' => [
                'index'     => route('products.index'),
                'show'      => route('products.show', $this->id),
                'update'    => route('products.update', $this->id),
                'store'     => route('products.store'),
                'destroy'   => route('products.destroy', $this->id),
                'variants'  => route('products.variants.index', $this->id),
            ],
            'permissions' => [
                'view' => [
                    'allowed' => $user?->can('view', $this->resource) ?? false,
                    'label' => __('actions.view'),
                ],
                'update' => [
                    'allowed' => $user?->can('update', $this->resource) ?? false,
                    'label' => __('actions.update'),
                ],
                'delete' => [
                    'allowed' => $user?->can('delete', $this->resource) ?? false,
                    'label' => __('actions.delete'),
                ],
                'archive' => [
                    'allowed' => $user?->can('archive', $this->resource) ?? false,
                    'label' => __('actions.archive'),
                ],
                'restore' => [
                    'allowed' => $user?->can('restore', $this->resource) ?? false,
                    'label' => __('actions.restore'),
                ],
            ],
        ];
    }
}
