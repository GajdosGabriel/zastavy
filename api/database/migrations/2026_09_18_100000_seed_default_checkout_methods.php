<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// Predvolené spôsoby dopravy a platby. Vkladajú sa len do prázdnych tabuliek,
// ďalej sa spravujú v admine (/admin/doprava, /admin/platby).
return new class extends Migration
{
    public function up(): void
    {
        $now = now();

        if (! DB::table('shipping_methods')->exists()) {
            DB::table('shipping_methods')->insert([
                ['name' => 'Kuriér', 'price' => 5.00, 'free_from_price' => 100.00, 'active' => true, 'sort_order' => 1, 'created_at' => $now, 'updated_at' => $now],
                ['name' => 'Osobný odber', 'price' => 0.00, 'free_from_price' => null, 'active' => true, 'sort_order' => 2, 'created_at' => $now, 'updated_at' => $now],
            ]);
        }

        if (! DB::table('payment_methods')->exists()) {
            DB::table('payment_methods')->insert([
                ['name' => 'Prevodom na účet', 'fee' => 0.00, 'type' => 'bank_transfer', 'active' => true, 'sort_order' => 1, 'created_at' => $now, 'updated_at' => $now],
                ['name' => 'Dobierka', 'fee' => 1.50, 'type' => 'cash_on_delivery', 'active' => true, 'sort_order' => 2, 'created_at' => $now, 'updated_at' => $now],
            ]);
        }
    }

    public function down(): void
    {
        // Údaje sa môžu medzitým meniť v admine, preto sa nemažú.
    }
};
