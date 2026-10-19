<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Obrázky vlajky EÚ a zástavy SR mali zľavu „30 %“ vpálenú v obrázku.
 * Nahrádzame ich čistými — zľavu teraz kreslí web podľa ceny variantu.
 */
return new class extends Migration
{
    private const MAP = [
        'public/eu-30.png'      => 'public/eu.jpg',
        'public/zastava-30.png' => 'public/zastava.jpg',
    ];

    public function up(): void
    {
        foreach (self::MAP as $old => $new) {
            DB::table('images')->where('path', $old)->update(['path' => $new]);
        }
    }

    public function down(): void
    {
        foreach (self::MAP as $old => $new) {
            DB::table('images')->where('path', $new)->update(['path' => $old]);
        }
    }
};
