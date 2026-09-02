<?php

namespace App\Http\Resources;

use Carbon\Carbon;
use Illuminate\Http\Resources\Json\JsonResource;

class StockResource extends JsonResource
{
    public function toArray($request)
    {
        $user = $request->user();

        $product = $this->product;
        $variant = $this->product_variant;

        // Záporný príjem je odpis (rozbité, stratené, inventúrna korekcia).
        $type = match (true) {
            (bool) $this->shipping_id => 'outgoing',
            $this->quantity < 0       => 'writeoff',
            default                   => 'incoming',
        };

        return [
            'id'                        => $this->id,
            'type'                      => $type,
            'shipping_id'               => $this->shipping_id,
            'order_id'                  => $this->order_id,
            'product_id'                => $this->product_id ?? $product?->id,
            'product_variant_id'        => $variant?->id,
            'variant_name'              => $variant?->name,
            'variant_code'              => $variant?->code,
            'company'                   => $this->shipping?->order?->customer?->company,
            'order_serial'              => $this->shipping?->order?->serial_number,
            'shipping_created_at_human' => $this->shipping
                ? Carbon::parse($this->shipping->created_at)->diffForHumans()
                : Carbon::parse($this->created_at)->diffForHumans(),
            'created_at_human'          => Carbon::parse($this->created_at)->diffForHumans(),
            'name'                      => $product?->name,
            'code'                      => $variant?->code ?? $product?->code,
            'product_unit_value'        => $product?->unit_value,
            // Tabuľka zobrazuje znamienko podľa typu — množstvo posielame kladné.
            'quantity'                  => abs((int) $this->quantity),
            'price'                     => $this->price,
            'total_price'               => $this->price !== null
                ? round(abs((int) $this->quantity) * (float) $this->price, 2)
                : null,
            'note'                      => $this->note,
            'status'                    => $this->statusData(),
            'endpoints' => [
                'destroy' => route('stocks.destroy', $this->id),
            ],
            'permissions' => [
                'delete' => [
                    'allowed' => $user?->can('delete', $this->resource) ?? false,
                    'label'   => __('actions.delete'),
                ],
            ],
        ];
    }
}
