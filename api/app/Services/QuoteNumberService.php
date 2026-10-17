<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

/** Číslo cenovej ponuky CP-RRRR-NNNN; počítadlo zdieľa tabuľku s objednávkami. */
class QuoteNumberService
{
    /** Volá sa v transakcii vytvárajúcej ponuku. */
    public function next(?int $year = null): string
    {
        $period = 'CP-'.($year ?? now()->year);
        DB::table('order_sequences')->insertOrIgnore(['period' => $period, 'last_number' => 0]);
        $sequence = DB::table('order_sequences')->where('period', $period)->lockForUpdate()->first();
        $next = (int) $sequence->last_number + 1;
        while (DB::table('sales_quotes')->where('number', $period.'-'.str_pad($next, 4, '0', STR_PAD_LEFT))->exists()) {
            $next++;
        }
        DB::table('order_sequences')->where('period', $period)->update(['last_number' => $next]);

        return $period.'-'.str_pad($next, 4, '0', STR_PAD_LEFT);
    }
}
