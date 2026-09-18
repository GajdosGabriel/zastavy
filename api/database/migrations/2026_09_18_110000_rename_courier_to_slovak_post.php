<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// Predvolená doprava je Slovenská pošta namiesto kuriéra. Mení sa len
// pôvodný nezmenený záznam, úpravy z adminu ostanú zachované.
return new class extends Migration
{
    public function up(): void
    {
        DB::table('shipping_methods')
            ->where('name', 'Kuriér')
            ->where('price', 5.00)
            ->whereNull('deleted_at')
            ->update(['name' => 'Slovenská pošta', 'updated_at' => now()]);
    }

    public function down(): void
    {
        //
    }
};
