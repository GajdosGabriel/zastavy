<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

class OrderNumberService
{
    /** Volá sa v transakcii vytvárajúcej objednávku. */
    public function next(string $period): string
    {
        DB::table('order_sequences')->insertOrIgnore(['period' => $period, 'last_number' => 0]);
        $sequence = DB::table('order_sequences')->where('period', $period)->lockForUpdate()->first();
        $next = (int) $sequence->last_number + 1;
        while (DB::table('orders')->where('serial_number', $period.'-'.str_pad($next, 4, '0', STR_PAD_LEFT))->exists()) {
            $next++;
        }
        DB::table('order_sequences')->where('period', $period)->update(['last_number' => $next]);

        return $period.'-'.str_pad($next, 4, '0', STR_PAD_LEFT);
    }
}
