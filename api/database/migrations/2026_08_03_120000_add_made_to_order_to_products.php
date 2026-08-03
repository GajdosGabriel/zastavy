<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tovar na zákazku sa nevyrába dopredu, takže sa preň sklad nesleduje
     * a karta nesmie hlásiť "vypredané".
     */
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->boolean('made_to_order')->default(false)->after('published');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn('made_to_order');
        });
    }
};
