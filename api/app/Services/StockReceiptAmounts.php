<?php

namespace App\Services;

class StockReceiptAmounts
{
    public static function line(int $quantity, float $price, float $discount, float $vat): array
    {
        $priceCents = (int) round($price * 100);
        $discountBasis = (int) round($discount * 100);
        $vatBasis = (int) round($vat * 100);
        // Celé centy a stotiny percenta: rovnaké zaokrúhlenie aj pri polcente.
        $net = intdiv($quantity * $priceCents * (10000 - $discountBasis) + 5000, 10000);
        $tax = intdiv($net * $vatBasis + 5000, 10000);
        return ['net' => $net / 100, 'tax' => $tax / 100, 'total' => ($net + $tax) / 100];
    }
}
