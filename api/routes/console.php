<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
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
 */
Schedule::command('app:customer-reviews-run --time-budget=25')
    ->everyFiveMinutes()
    ->withoutOverlapping();
