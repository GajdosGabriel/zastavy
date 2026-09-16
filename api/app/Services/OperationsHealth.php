<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Throwable;

class OperationsHealth
{
    public function checks(): array
    {
        $checks = [];
        $probe = function (string $name, callable $check) use (&$checks) {
            try {
                $checks[$name] = $check() ? 'ok' : 'failed';
            } catch (Throwable) {
                // Výstup monitora neobsahuje heslá, SQL ani payload zákazníka.
                $checks[$name] = 'failed';
            }
        };
        $probe('database', fn () => count(DB::select('SELECT 1')) === 1);
        $probe('cache', function () {
            $key = 'ops:probe:'.Str::uuid();
            Cache::put($key, 'ok', 30);
            $ok = Cache::get($key) === 'ok';
            Cache::forget($key);

            return $ok;
        });
        $probe('scheduler', fn () => $this->fresh('ops:scheduler-heartbeat'));
        $connection = config('queue.default');
        $driver = config('queue.connections.'.$connection.'.driver');
        if (in_array($driver, ['sync', 'null', 'deferred', 'background'], true)) {
            $checks['queue_worker'] = 'failed';
        } else {
            $probe('queue_worker', fn () => $this->fresh('ops:worker-heartbeat'));
            $probe('queue_backlog', fn () => Queue::connection($connection)->size() <= config('operations.queue_max_size'));
        }
        $probe('failed_jobs', function () {
            if (! in_array(config('queue.failed.driver'), ['database', 'database-uuids'], true)) {
                return false;
            }

            return ! DB::connection(config('queue.failed.database'))->table(config('queue.failed.table'))
                ->where('failed_at', '>=', now()->subHours(config('operations.failed_job_window_hours')))->exists();
        });

        return $checks;
    }

    private function fresh(string $key): bool
    {
        $timestamp = Cache::get($key);

        return is_numeric($timestamp) && $timestamp <= now()->timestamp
            && now()->timestamp - $timestamp <= config('operations.heartbeat_max_age');
    }
}
