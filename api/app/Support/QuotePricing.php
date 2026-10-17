<?php

namespace App\Support;

use App\Models\ProductVariant;
use App\Models\ShippingMethod;
use Illuminate\Validation\ValidationException;

/**
 * Ceny cenovej ponuky. Obsluha zadáva ceny bez DPH, objednávky však držia
 * jednotkovú cenu s DPH (`order_products.price`). Preto sa cena s DPH počíta
 * z jednotkovej ceny po zľave a zaokrúhli na centy — súčet ponuky s DPH je
 * potom zhodný so súčtom objednávky, ktorá z nej vznikne.
 */
class QuotePricing
{
    /** Sadzba DPH dopravy (štandardná sadzba v SR). */
    public const SHIPPING_VAT = 23;

    public static function rules(bool $strict): array
    {
        $required = $strict ? 'required' : 'nullable';

        return [
            'items' => [$strict ? 'required' : 'present', 'array', $strict ? 'min:1' : 'min:0', 'max:100'],
            'items.*.is_custom' => 'sometimes|boolean',
            'items.*.variant_id' => 'required_unless:items.*.is_custom,true|nullable|integer',
            'items.*.name' => 'required_if:items.*.is_custom,true|nullable|string|max:200',
            'items.*.unit' => 'nullable|string|max:20',
            'items.*.vat' => ['required_if:items.*.is_custom,true', 'nullable', 'integer', 'in:23,20,10,0'],
            'items.*.description' => 'nullable|string|max:2000',
            'items.*.quantity' => 'required|integer|min:1|max:100000',
            'items.*.price_net' => 'required|numeric|min:0|max:999999.99',
            'items.*.discount_percent' => 'nullable|numeric|min:0|max:100',
            'discount_percent' => 'nullable|numeric|min:0|max:100',
            'shipping_method_id' => $required.'|integer',
            'shipping_price_net' => $required.'|numeric|min:0|max:999999.99',
            'terms' => 'nullable|string|max:10000',
            'valid_until' => $required.'|date_format:Y-m-d'.($strict ? '|after_or_equal:today' : ''),
        ];
    }

    /** Vyskladá položky so snímkou produktu a dopočítanými cenami. */
    public static function items(array $input, bool $strict): array
    {
        $variants = ProductVariant::with('product')->whereIn('id', collect($input)->pluck('variant_id')->filter())->get()->keyBy('id');

        return collect($input)->map(function (array $item, int $index) use ($variants, $strict) {
            $custom = ! empty($item['is_custom']);
            $variant = $custom ? null : $variants->get($item['variant_id']);
            if (! $custom && ! $variant?->product) {
                throw ValidationException::withMessages(["items.$index.variant_id" => 'Variant neexistuje.']);
            }
            if ($strict && $variant && $item['quantity'] < max(1, (int) $variant->min_order)) {
                throw ValidationException::withMessages(["items.$index.quantity" => 'Množstvo je pod minimálnym odberom ('.$variant->min_order.').']);
            }
            $snapshot = $custom
                ? ['name' => trim($item['name']), 'code' => null, 'unit_value' => ($item['unit'] ?? '') ?: 'ks', 'vat' => (int) $item['vat'], 'variant_name' => null, 'variant_code' => null]
                : ['name' => $variant->product->name, 'code' => $variant->product->code, 'unit_value' => $variant->product->unit_value ?: 'ks', 'vat' => (int) $variant->product->vat, 'variant_name' => $variant->name, 'variant_code' => $variant->code];
            $line = self::line((int) $item['quantity'], (float) $item['price_net'], (float) ($item['discount_percent'] ?? 0), $snapshot['vat']);
            if ($line['line_gross'] > 999999.99) {
                throw ValidationException::withMessages(["items.$index.price_net" => 'Suma jednej položky nesmie presiahnuť 999 999,99 €.']);
            }

            return ['is_custom' => $custom, 'product_id' => $variant?->product_id, 'variant_id' => $variant?->id, 'description' => ($item['description'] ?? null) ?: null,
                'snapshot' => $snapshot] + $line;
        })->values()->all();
    }

    /** `price` je jednotková cena s DPH — kľúč zdieľa so staršími verziami a s objednávkou. */
    public static function line(int $quantity, float $priceNet, float $discount, int $vat): array
    {
        $unitNet = round($priceNet * (1 - $discount / 100), 2);
        $unitGross = round($unitNet * (1 + $vat / 100), 2);

        return ['quantity' => $quantity, 'price_net' => round($priceNet, 2), 'discount_percent' => round($discount, 2), 'unit_net' => $unitNet, 'price' => $unitGross,
            'line_net' => round($quantity * $unitNet, 2), 'line_gross' => round($quantity * $unitGross, 2)];
    }

    public static function shipping(?int $methodId, mixed $priceNet, bool $strict): ?array
    {
        if (! $methodId) {
            return null;
        }
        $method = ShippingMethod::when($strict, fn ($q) => $q->where('active', true))->find($methodId);
        if (! $method) {
            throw ValidationException::withMessages(['shipping_method_id' => 'Vyberte aktívnu dopravu.']);
        }
        $net = round((float) $priceNet, 2);

        return ['id' => $method->id, 'name' => $method->name, 'price_net' => $net, 'vat' => self::SHIPPING_VAT, 'price' => round($net * (1 + self::SHIPPING_VAT / 100), 2)];
    }

    /** Celková zľava ide do objednávky ako percentuálna úprava ceny (OrderPricing). */
    public static function totals(array $items, float $discount, ?array $shipping): array
    {
        $itemsNet = round(collect($items)->sum('line_net'), 2);
        $itemsGross = round(collect($items)->sum('line_gross'), 2);
        $discountNet = round($itemsNet * $discount / 100, 2);
        $discountGross = round($itemsGross * $discount / 100, 2);
        $shippingNet = (float) ($shipping['price_net'] ?? 0);
        $shippingGross = (float) ($shipping['price'] ?? 0);
        $net = round($itemsNet - $discountNet + $shippingNet, 2);
        $gross = round($itemsGross - $discountGross + $shippingGross, 2);

        return ['items_net' => $itemsNet, 'items_gross' => $itemsGross, 'discount_percent' => round($discount, 2), 'discount_net' => $discountNet, 'discount_gross' => $discountGross,
            'shipping_net' => $shippingNet, 'shipping_gross' => $shippingGross, 'net' => $net, 'vat' => round($gross - $net, 2), 'gross' => $gross];
    }
}
