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

        if (! $stock->wasChanged('quantity')) {
            return;
        }

        $before = (int) $stock->getOriginal('quantity');
        $after  = (int) $stock->quantity;
        $sign   = $stock->shipping_id ? -1 : 1;

        $this->apply($stock, ($after - $before) * $sign);
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

        return $stock->shipping_id ? -$quantity : $quantity;
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

        $variant = ProductVariant::find($variantId);

        // null = sklad sa pri tomto variante nesleduje, nesmieme ho "zapnúť".
        if (! $variant || $variant->quantity === null) {
            return;
        }

        $variant->forceFill(['quantity' => (int) $variant->quantity + $delta])->saveQuietly();
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
