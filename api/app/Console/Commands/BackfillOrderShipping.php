<?php

namespace App\Console\Commands;

use App\Enums\OrderStatus;
use App\Models\Order;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Dorobí expedíciu starým objednávkam, ktoré ju nikdy nedostali.
 *
 * Importované historické objednávky nemajú žiadny záznam v `stocks`, takže ich
 * OrderFilter::isActive() dodnes vyhodnocuje ako aktívne. Príkaz im vytvorí
 * dodací list (shippings) a k nemu skladové pohyby na nevystornované množstvo,
 * všetko datované dňom vzniku objednávky — objednávka sa tým stane vybavenou.
 *
 * Zapisuje sa priamo cez query builder, teda bez modelových eventov: StockObserver
 * by inak historickou expedíciou odpísal množstvo z aktuálneho stavu skladu.
 */
class BackfillOrderShipping extends Command
{
    protected $signature = 'orders:backfill-shipping
                            {--before= : Len objednávky vytvorené pred týmto dátumom (napr. 2021-01-01), default rok dozadu}
                            {--notice : Pridá aj záznam o notifikácii, nech expedície nevyskočia vo filtri Nenotifikované}
                            {--dry-run : Iba vypíše, čo by sa zmenilo — nič neuloží}';

    protected $description = 'Vytvorí starým nevybaveným objednávkam expedíciu datovanú dňom vzniku objednávky';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $notice = (bool) $this->option('notice');

        try {
            $before = $this->option('before')
                ? Carbon::parse($this->option('before'))->startOfDay()
                : now()->subYear()->startOfDay();
        } catch (\Throwable $e) {
            $this->error('Neplatný dátum v --before.');

            return self::FAILURE;
        }

        // Rovnaké podmienky ako OrderFilter::isActive() — berieme presne to,
        // čo sa dnes zobrazuje ako aktívne.
        $ordered = '(select coalesce(sum(op.quantity), 0) from order_products op where op.order_id = orders.id and op.deleted_at is null)';
        $storno  = '(select coalesce(sum(op.storno), 0) from order_products op where op.order_id = orders.id and op.deleted_at is null)';

        $orders = Order::query()
            ->with('orderProducts')
            ->where('created_at', '<', $before)
            ->whereDoesntHave('stocks')
            ->where(function ($query) {
                $query->whereNull('status')
                    ->orWhere('status', '!=', OrderStatus::Cancelled->value);
            })
            ->whereRaw("{$ordered} > {$storno}")
            ->orderBy('id')
            ->get();

        $this->info('Hranica: objednávky vytvorené pred '.$before->format('d.m.Y'));
        $this->info('Na expedovanie: '.$orders->count());

        if ($orders->isEmpty()) {
            return self::SUCCESS;
        }

        $plan = [];

        foreach ($orders as $order) {
            $items = $order->orderProducts
                ->map(fn ($item) => [
                    'order_product_id' => $item->id,
                    'quantity'         => (int) $item->quantity - (int) $item->storno,
                ])
                ->filter(fn ($item) => $item['quantity'] > 0)
                ->values();

            if ($items->isEmpty()) {
                continue;
            }

            $plan[] = ['order' => $order, 'items' => $items];

            if ($this->output->isVerbose()) {
                $this->line("  #{$order->id}: ".($order->serial_number ?? '—')
                    .' — '.$order->created_at->format('d.m.Y')
                    .' — položiek: '.$items->count()
                    .', ks: '.$items->sum('quantity'));
            }
        }

        if ($dryRun) {
            $this->comment('Dry-run — nič sa neuložilo.');

            return self::SUCCESS;
        }

        DB::transaction(function () use ($plan, $notice) {
            foreach ($plan as $row) {
                $order = $row['order'];
                $at    = $order->created_at;

                $shippingId = DB::table('shippings')->insertGetId([
                    'status'     => 'active',
                    'order_id'   => $order->id,
                    'created_at' => $at,
                    'updated_at' => $at,
                ]);

                DB::table('stocks')->insert(
                    $row['items']->map(fn ($item) => [
                        'status'           => 'active',
                        'order_id'         => $order->id,
                        'shipping_id'      => $shippingId,
                        'order_product_id' => $item['order_product_id'],
                        'quantity'         => $item['quantity'],
                        'created_at'       => $at,
                        'updated_at'       => $at,
                    ])->all()
                );

                if ($notice) {
                    DB::table('notices')->insert([
                        'status'        => 'active',
                        'fileable_id'   => $shippingId,
                        'fileable_type' => \App\Models\Shipping::class,
                        'notice'        => 'email',
                        'created_at'    => $at,
                        'updated_at'    => $at,
                    ]);
                }
            }
        });

        $this->info('Hotovo — expedovaných '.count($plan).' objednávok.');

        return self::SUCCESS;
    }
}
