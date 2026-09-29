<?php

namespace App\Support;

class OrderPricing
{
    public static function rules(bool $staff = true): array
    {
        return [
            'price_adjustment' => $staff ? ['sometimes', 'nullable', 'array:direction,type,value,label'] : ['prohibited'],
            'price_adjustment.direction' => ['required_with:price_adjustment', 'in:discount,surcharge'],
            'price_adjustment.type' => ['required_with:price_adjustment', 'in:fixed,percent'],
            'price_adjustment.value' => ['required_with:price_adjustment', 'numeric', 'min:0', ($staff && request('price_adjustment.direction') === 'discount' && request('price_adjustment.type') === 'percent' ? 'max:100' : 'max:999999')],
            'price_adjustment.label' => ['nullable', 'string', 'max:200'],
        ];
    }

    public static function amount(float $subtotal, ?array $adjustment, float $coupon = 0): float
    {
        if (! $adjustment) {
            return 0;
        }
        $amount = round($adjustment['type'] === 'percent' ? $subtotal * (float) $adjustment['value'] / 100 : (float) $adjustment['value'], 2);

        return $adjustment['direction'] === 'discount' ? -round(min(max(0, $subtotal - $coupon), $amount), 2) : $amount;
    }
}
