<?php

namespace App\Actions;

use App\Http\Controllers\Api\SuperAdmin\CouponSettingsController;
use App\Models\Coupon;
use App\Models\Order;
use App\Notifications\CouponIssued;
use Carbon\Carbon;

class IssueCouponForOrder
{
    public function handle(Order $order): ?Coupon
    {
        if (! $order->wants_coupon) {
            return null;
        }

        $email = $order->email ?? $order->customer?->email;

        if (! $email) {
            return null;
        }

        // Zabráni duplicitnému vydaniu kupóna pre tú istú objednávku
        if (Coupon::where('source_order_id', $order->id)->exists()) {
            return null;
        }

        $settings   = CouponSettingsController::read();
        $orderTotal = $order->orderProducts->sum('total');
        $minOrder   = $this->calculateMinOrder($orderTotal, $settings);
        $validFrom  = Carbon::today()->addDays((int) $settings['delay_days']);
        $validTo    = $validFrom->copy()->addDays((int) $settings['valid_days']);

        $coupon = Coupon::create([
            'code'            => $this->generateUniqueCode(),
            'type'            => 'percent',
            'value'           => (int) $settings['discount_percent'],
            'min_order_price' => $minOrder,
            'usage_limit'     => 1,
            'used_count'      => 0,
            'valid_from'      => $validFrom,
            'valid_to'        => $validTo,
            'active'          => true,
            'email'           => $email,
            'source_order_id' => $order->id,
        ]);

        $notifiable = $order->customer ?? $order->user;
        $notifiable?->notify(new CouponIssued($coupon, $order));

        return $coupon;
    }

    private function calculateMinOrder(float $orderTotal, array $settings): float
    {
        $raw = max(
            (float) $settings['min_order_floor'],
            $orderTotal * (float) $settings['min_order_multiplier']
        );
        // Zaokrúhli nahor na celé desiatky (napr. 83 → 90)
        return ceil($raw / 10) * 10;
    }

    private function generateUniqueCode(): string
    {
        $chars = 'ABCDEFGHJKMNPQRSTUVWXYZ23456789';

        do {
            // 8 znakov bez O, I, L aby nedochádzalo k zámene
            $code = implode('', array_map(
                fn () => $chars[random_int(0, strlen($chars) - 1)],
                range(1, 8)
            ));
        } while (Coupon::where('code', $code)->exists());

        return $code;
    }
}
