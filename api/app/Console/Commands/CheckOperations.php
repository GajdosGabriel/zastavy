<?php

namespace App\Console\Commands;

use App\Services\OperationsHealth;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class CheckOperations extends Command
{
    protected $signature = 'ops:check {--json : Strojovo čitateľný výstup}';

    protected $description = 'Overí DB, cache, scheduler, worker, veľkosť fronty a zlyhané úlohy';

    public function handle(OperationsHealth $health): int
    {
        $checks = $health->checks();
        $healthy = ! in_array('failed', $checks, true);
        if ($this->option('json')) {
            $this->line(json_encode(['healthy' => $healthy, 'checks' => $checks], JSON_THROW_ON_ERROR));
        } else {
            $this->table(['Kontrola', 'Stav'], collect($checks)->map(fn ($status, $name) => [$name, $status])->all());
        }
        if (! $healthy) {
            Log::error('operations.unhealthy', ['checks' => $checks]);
        }

        return $healthy ? self::SUCCESS : self::FAILURE;
    }
}
