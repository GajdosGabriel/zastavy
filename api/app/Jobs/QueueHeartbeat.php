<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Cache;

class QueueHeartbeat implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 15;

    public function handle(): void
    {
        Cache::store(config('operations.heartbeat_store'))->put('ops:worker-heartbeat', now()->timestamp, now()->addDay());
    }
}
