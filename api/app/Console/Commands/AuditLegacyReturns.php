<?php

namespace App\Console\Commands;

use App\Models\Stock;
use Illuminate\Console\Command;

class AuditLegacyReturns extends Command
{
    protected $signature = 'stocks:audit-returns';

    protected $description = 'Vypíše staré vratky bez explicitného skladového účinku; nemení údaje';

    public function handle(): int
    {
        $count = 0;
        Stock::whereNotNull('order_return_id')->whereNull('inventory_delta')
            ->with('orderProduct')->chunkById(200, function ($stocks) use (&$count) {
                foreach ($stocks as $stock) {
                    $this->line(json_encode(['stock_id' => $stock->id, 'return_id' => $stock->order_return_id,
                        'variant_id' => $stock->product_variant_id ?? $stock->orderProduct?->product_variant_id,
                        'legacy_delta' => (int) $stock->quantity], JSON_UNESCAPED_UNICODE));
                    $count++;
                }
            });
        $this->info('Vratky na preverenie: '.$count);
        $this->comment('Historické stavy sa nemenili. Porovnajte so skutočnou zásobou a už vykonanými inventúrnymi opravami.');

        return self::SUCCESS;
    }
}
