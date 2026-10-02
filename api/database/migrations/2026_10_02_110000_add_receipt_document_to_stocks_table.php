<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Údaje príjemky: od koho tovar prišiel, na aký doklad a kedy.
     * Doteraz sa to písalo voľným textom do poznámky a nedalo sa podľa toho hľadať.
     */
    public function up(): void
    {
        Schema::table('stocks', function (Blueprint $table) {
            $table->string('supplier')->nullable()->after('note');
            $table->string('document_number', 64)->nullable()->after('supplier');
            // Dátum fyzického príjmu — môže byť skôr, než sa pohyb zapísal do systému.
            $table->date('received_at')->nullable()->after('document_number');

            $table->index('document_number');
        });
    }

    public function down(): void
    {
        Schema::table('stocks', function (Blueprint $table) {
            $table->dropIndex(['document_number']);
            $table->dropColumn(['supplier', 'document_number', 'received_at']);
        });
    }
};
