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
            'name' => $this->name,
            'slug' => $this->slug,
            'description' => $this->description,
            'price' => $this->price,
            'sale_price' => $this->sale_price,
            'quantity' => $this->quantity,
            'published' => $this->published,
            'status' => $status->toArray(),
            'status_options' => ModelStatus::allowedForUser($user),
            'vat' => $this->vat,
            'discount' => $this->discount,
            'thumb' => url($this->thumb),
            'min_order' => $this->min_order,
            'active_price' => $this->active_price,
            'unit_value' => $this->unit_value,
            'input_order' => $this->min_order,
            'images' => ImageResource::collection($this->images),
            'categories' => CategoryResource::collection($this->categories),
            // 'stock_quantity' => $this->stock->sum('quantity'),

            'endpoints' => [
                'index'     => route('products.index'),
                'show'      => route('products.show', $this->id),
                'update'    => route('products.update', $this->id),
                'store'     => route('products.store'),
                'destroy'   => route('products.destroy', $this->id),
            ],
            'permissions' => [
                'view' => $user?->can('view', $this->resource) ?? false,
                'update' => $user?->can('update', $this->resource) ?? false,
                'delete' => $user?->can('delete', $this->resource) ?? false,
                'archive' => $user?->can('archive', $this->resource) ?? false,
                'restore' => $user?->can('restore', $this->resource) ?? false,
            ],
        ];
    }
}
