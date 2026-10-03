<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class StockReceiptResource extends JsonResource
{
    public function toArray($request): array
    {
        $items = $this->items->map(function ($stock) {
            $amounts = \App\Services\StockReceiptAmounts::line((int) $stock->quantity, (float) $stock->receipt_unit_price, (float) $stock->receipt_discount, (float) $stock->receipt_vat);

            return array_merge($stock->receipt_item_snapshot ?? [], [
                'id' => $stock->id,
                'product_variant_id' => $stock->product_variant_id,
                'quantity' => (int) $stock->quantity,
                'price' => (float) $stock->receipt_unit_price,
                'discount' => (float) $stock->receipt_discount,
                'vat' => (float) $stock->receipt_vat,
                'note' => $stock->note,
                ...$amounts,
            ]);
        });

        return [
            'id' => $this->id,
            'number' => $this->number,
            'received_at' => $this->received_at->toDateString(),
            'supplier' => $this->supplier,
            'supplier_address' => $this->supplier_address,
            'supplier_ico' => $this->supplier_ico,
            'supplier_dic' => $this->supplier_dic,
            'supplier_vat_id' => $this->supplier_vat_id,
            'document_number' => $this->document_number,
            'warehouse' => $this->warehouse,
            'received_by' => $this->received_by,
            'created_by_name' => $this->created_by_name,
            'created_at' => $this->created_at->toIso8601String(),
            'note' => $this->note,
            'cancelled_at' => $this->cancelled_at?->toIso8601String(),
            'cancellation_reason' => $this->cancellation_reason,
            'items' => $items,
            'net' => round($items->sum('net'), 2),
            'tax' => round($items->sum('tax'), 2),
            'total' => round($items->sum('total'), 2),
            'vat_summary' => $items->groupBy('vat')->map(fn ($rows, $rate) => [
                'rate' => (float) $rate, 'net' => round($rows->sum('net'), 2), 'tax' => round($rows->sum('tax'), 2),
            ])->values(),
        ];
    }
}
