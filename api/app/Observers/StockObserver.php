<?php

namespace App\Observers;

use App\Models\ProductVariant;
use App\Models\Stock;

/**
 * Drží `product_variants.quantity` v súlade s pohybmi skladu.
 *
 * Stĺpec quantity je to, čím sa riadi dostupnosť v e-shope (ProductVariant::isInStock).
 * Bez tohto observera bol úplne odtrhnutý od tabuľky stocks — naskladnenie
 * ani expedícia ním nepohli.
 *
 * Zámerne sa upravuje o rozdiel (nie prepočet z celej histórie), aby ručne
 * nastavený počiatočný stav zostal zachovaný. quantity === null znamená
 * "sklad sa nesleduje" a v tom prípade sa nerobí nič.
 */
class StockObserver
{
    public function creating(Stock $stock): void
    {
        // Zámok pred INSERTom: FK inak získa zdieľaný zámok variantu a dva
        // súbežné príjmy sa pri následnom UPDATE môžu vzájomne zablokovať.
        if ($id = $this->variantId($stock)) {
            ProductVariant::withTrashed()->whereKey($id)->lockForUpdate()->first();
        }
    }

    public function created(Stock $stock): void
    {
        $this->apply($stock, $this->delta($stock));
    }

    public function updated(Stock $stock): void
    {
        // Pri soft delete/restore prichádza aj updated — tie rieši deleted/restored.
        if ($stock->wasChanged('deleted_at')) {
            return;
        }

        if (! $stock->wasChanged(['quantity', 'inventory_delta'])) {
            return;
        }
        $before = $stock->getOriginal('inventory_delta') !== null
            ? (int) $stock->getOriginal('inventory_delta')
            : (int) $stock->getOriginal('quantity') * ($stock->shipping_id ? -1 : 1);
        $this->apply($stock, $this->delta($stock) - $before);
    }

    public function deleted(Stock $stock): void
    {
        $this->apply($stock, -$this->delta($stock));
    }

    public function restored(Stock $stock): void
    {
        $this->apply($stock, $this->delta($stock));
    }

    /**
     * O koľko pohyb mení stav na sklade. Expedícia stav znižuje, príjem zvyšuje,
     * odpis je príjem so záporným množstvom.
     */
    private function delta(Stock $stock): int
    {
        $quantity = (int) $stock->quantity;

        return $stock->inventory_delta !== null ? (int) $stock->inventory_delta : ($stock->shipping_id ? -$quantity : $quantity);
    }

    private function apply(Stock $stock, int $delta): void
    {
        if ($delta === 0) {
            return;
        }

        $variantId = $this->variantId($stock);

        if (! $variantId) {
            return;
        }

        // Jediný SQL UPDATE: súbežné pohyby si nemôžu prepísať stav.
        ProductVariant::withTrashed()->whereKey($variantId)
            ->whereNotNull('quantity')->increment('quantity', $delta);
    }

    /**
     * Príjem má variant priamo, výdaj cez položku objednávky.
     */
    private function variantId(Stock $stock): ?int
    {
        if ($stock->product_variant_id) {
            return (int) $stock->product_variant_id;
        }

        return $stock->orderProduct?->product_variant_id;
    }
}
