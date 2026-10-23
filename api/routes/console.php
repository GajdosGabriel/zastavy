<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/*
 * Post-kontrola údajov zákazníkov.
 *
 * Beží každých päť minút a v malej dávke: každý riadok je až jedno volanie
 * registra a jedno volanie OpenAI, teda sekundy. Časový strop drží beh pod
 * pol minútou, aby sa zmestil aj do webcronu, kde všetky príkazy bežia
 * sekvenčne v jednom HTTP requeste.
 *
 * Na hostingu musí byť naplánované `php artisan schedule:run` každú minútu.
 *
 * Zámky `withoutOverlapping` majú expiráciu 10 minút: keď DB vypadne pri ich
 * uvoľňovaní, zaseknutý zámok neblokuje úlohu predvolených 24 hodín.
 */
Schedule::command('app:customer-reviews-run --time-budget=25')
    ->everyFiveMinutes()
    ->withoutOverlapping(10);


if (config('operations.enabled')) {
    Schedule::call(function () {
        \Illuminate\Support\Facades\Cache::put('ops:scheduler-heartbeat', now()->timestamp, now()->addDay());
        // Najviac jeden probe za 5 minút; funguje aj pri viacerých scheduleroch.
        if (\Illuminate\Support\Facades\Cache::add('ops:probe-dispatched', true, 290)) {
            \App\Jobs\QueueHeartbeat::dispatch();
        }
    })->name('operations-heartbeats')->everyFiveMinutes()->withoutOverlapping(10);
    Schedule::command('ops:check')->everyFiveMinutes()->withoutOverlapping(10);
}

// Opraviteľné chyby z laravel.log e-mailom (config/logging.php → report).
Schedule::command('logs:report-errors')->everyTenMinutes()->withoutOverlapping(10);

// Denník udalostí drží len posledných 30 dní (config/logging.php).
Schedule::command('model:prune', ['--model' => \App\Models\SystemLog::class])->dailyAt('03:25');

/*
 * DatabaseStore exspirovaný riadok zmaže až pri čítaní. Počítadlá throttle
 * (jeden-dva riadky na každú IP a limit) sa po exspirácii už neprečítajú,
 * takže pri návale botov by tabuľka `cache` rástla bez konca.
 */
Artisan::command('cache:prune-expired', function () {
    $table = config('cache.stores.database.table') ?: 'cache';
    $db = DB::connection(config('cache.stores.database.connection'));
    $deleted = 0;
    foreach ([$table, config('cache.stores.database.lock_table') ?: 'cache_locks'] as $t) {
        do {
            // Po dávkach, aby jeden DELETE nedržal zámok na celej tabuľke.
            $batch = $db->table($t)->where('expiration', '<', time())->limit(5000)->delete();
            $deleted += $batch;
        } while ($batch > 0);
    }
    $this->info('Zmazané exspirované riadky cache: '.$deleted);
})->purpose('Vymaže exspirované riadky databázovej cache (počítadlá throttle, zámky)');
Schedule::command('cache:prune-expired')->hourly()->withoutOverlapping(10);

// Tokeny staršie ako sanctum.expiration (30 dní) už neplatia.
Schedule::command('sanctum:prune-expired', ['--hours' => 24])->dailyAt('03:45');

Artisan::command('order-emails:prune-bodies', function () {
    $this->info('Vymazaný obsah e-mailov: '.\App\Models\OrderEmail::pruneBodies());
})->purpose('Vymaže obsah starých e-mailov v histórii objednávok, záznam o odoslaní ponechá');
Schedule::command('order-emails:prune-bodies')->dailyAt('03:35')->withoutOverlapping(10);
