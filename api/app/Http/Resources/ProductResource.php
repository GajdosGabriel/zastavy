<?php

namespace App\Http\Resources;

use App\Enums\ModelStatus;
use App\Http\Resources\Concerns\StaffMeta;
use Illuminate\Support\Facades\Storage;
use Illuminate\Http\Resources\Json\JsonResource;

class ProductResource extends JsonResource
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
        $user = $request->user();
        $staff = $this->staffUser($request);
        $status = ModelStatus::fromProduct($this->resource);

        // Dostupnosť variantu závisí od príznaku na produkte. Reláciu doplníme
        // dopredu, aby si ju variant nedoťahoval vlastným dotazom.
        if ($this->resource->relationLoaded('variants')) {
            $this->resource->variants->each->setRelation('product', $this->resource);
        }

        if ($this->resource->relationLoaded('defaultVariant')) {
            $this->resource->defaultVariant?->setRelation('product', $this->resource);
        }

        return [
            'id' => $this->id,
            'code' => $this->code,
            'name' => $this->name,
            'slug' => $this->slug,
            'description' => $this->description,
            'published' => $this->published,
            'made_to_order' => (bool) $this->made_to_order,
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

            // Prázdne polia pre verejnosť — admin UI číta `Object.keys(product.endpoints)`,
            // takže kľúč musí v odpovedi zostať.
            'endpoints' => $staff ? [
                'index'     => route('products.index'),
                'show'      => route('products.show', $this->id),
                'update'    => route('products.update', $this->id),
                'store'     => route('products.store'),
                'destroy'   => route('products.destroy', $this->id),
                'variants'  => route('products.variants.index', $this->id),
            ] : [],
            'permissions' => $staff ? [
                'view' => [
                    'allowed' => $staff->can('view', $this->resource),
                    'label' => __('actions.view'),
                ],
                'update' => [
                    'allowed' => $staff->can('update', $this->resource),
                    'label' => __('actions.update'),
                ],
                'delete' => [
                    'allowed' => $staff->can('delete', $this->resource),
                    'label' => __('actions.delete'),
                ],
                'archive' => [
                    'allowed' => $staff->can('archive', $this->resource),
                    'label' => __('actions.archive'),
                ],
                'restore' => [
                    'allowed' => $staff->can('restore', $this->resource),
                    'label' => __('actions.restore'),
                ],
            ] : [],
        ];
    }
}
