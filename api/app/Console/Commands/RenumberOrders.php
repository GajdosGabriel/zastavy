<?php

namespace App\Console\Commands;

use App\Models\Order;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Prečísluje všetky objednávky na aktuálny formát RRRR-MM-NNNN.
 *
 * Importované historické objednávky majú staré formáty (napr. "001/2022"),
 * ktoré navyše nie sú unikátne. Poradie sa odvodzuje z created_at (pri
 * rovnakom čase z id), takže výsledok je zhodný s tým, čo generuje
 * StoreOrder::serialNumber() pri vytvorení novej objednávky.
 */
class RenumberOrders extends Command
{
    protected $signature = 'orders:renumber
                            {--dry-run : Iba vypíše, čo by sa zmenilo — nič neuloží}';

    protected $description = 'Prečísluje objednávky na formát RRRR-MM-NNNN podľa created_at';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');

        // withTrashed: soft-deleted objednávky musia ostať v poradí, inak by sa
        // ich číslo pridelilo druhýkrát novej objednávke.
        $orders = Order::withTrashed()
            ->select('id', 'serial_number', 'created_at')
            ->orderBy('created_at')
            ->orderBy('id')
            ->get();

        $counters = [];
        $updates = [];

        foreach ($orders as $order) {
            $period = $order->created_at->format('Y-m');
            $counters[$period] = ($counters[$period] ?? 0) + 1;

            $serial = $period.'-'.str_pad((string) $counters[$period], 4, '0', STR_PAD_LEFT);

            if ($order->serial_number === $serial) {
                continue;
            }

            $updates[] = [
                'id'   => $order->id,
                'from' => $order->serial_number,
                'to'   => $serial,
            ];
        }

        $this->info('Objednávok spolu: '.$orders->count());
        $this->info('Na prečíslovanie: '.count($updates));

        if ($this->output->isVerbose()) {
            foreach ($updates as $update) {
                $this->line("  #{$update['id']}: ".($update['from'] ?? '—')." → {$update['to']}");
            }
        }

        if ($dryRun) {
            $this->comment('Dry-run — nič sa neuložilo.');

            return self::SUCCESS;
        }

        if (! $updates) {
            $this->info('Číslovanie je už aktuálne.');

            return self::SUCCESS;
        }

        // Priamy update cez query builder — nechceme hýbať updated_at.
        DB::transaction(function () use ($updates) {
            foreach ($updates as $update) {
                DB::table('orders')
                    ->where('id', $update['id'])
                    ->update(['serial_number' => $update['to']]);
            }
        });

        $this->info('Hotovo — prečíslovaných '.count($updates).' objednávok.');

        return self::SUCCESS;
    }
}
