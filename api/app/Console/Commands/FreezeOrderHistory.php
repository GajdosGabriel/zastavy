<?php

namespace App\Console\Commands;

use App\Models\Order;
use App\Models\OrderProduct;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class FreezeOrderHistory extends Command
{
    protected $signature = 'orders:freeze-history {--apply : Uložiť rekonštruované historické údaje}';

    protected $description = 'Zmrazí aktuálne známe údaje starých objednávok; označí ich ako rekonštruované';

    public function handle(): int
    {
        $this->info('Objednávky bez odtlačku: '.Order::withTrashed()->whereNull('billing_snapshot')->count());
        if (! $this->option('apply')) {
            $this->comment('Náhľad; na uloženie použite --apply.');

            return self::SUCCESS;
        }
        Order::withTrashed()->whereNull('billing_snapshot')->chunkById(200, function ($orders) {
            foreach ($orders as $candidate) {
                DB::transaction(function () use ($candidate) {
                    $order = Order::withTrashed()->whereKey($candidate->id)->lockForUpdate()->first();
                    if ($order->billing_snapshot !== null) {
                        return;
                    }
                    Order::withoutTimestamps(fn () => $order->forceFill([
                        'billing_snapshot' => $order->billingSnapshot(), 'snapshot_source' => 'reconstructed',
                        'shipping_method_name' => $order->shippingMethod?->name,
                        'payment_method_name' => $order->paymentMethod?->name,
                    ])->saveQuietly());
                });
            }
        });
        OrderProduct::withTrashed()->whereNull('product_snapshot')->chunkById(200, function ($items) {
            foreach ($items as $item) {
                // Podmienený update neprepíše odtlačok uložený súbežne.
                DB::table('order_products')->where('id', $item->id)->whereNull('product_snapshot')
                    ->update(['product_snapshot' => json_encode($item->productSnapshot(), JSON_THROW_ON_ERROR)]);
            }
        });
        $this->info('Historické údaje uložené ako rekonštruované.');

        return self::SUCCESS;
    }
}
