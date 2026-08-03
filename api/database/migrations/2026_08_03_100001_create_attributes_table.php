<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Globálna taxonómia vlastností (Rozmer, Materiál, Uchytenie, Potlač).
     * Zdieľaná naprieč katalógom — preto sa dá filtrovať a robiť SEO stránky
     * typu /zastavy/rozmer-100x150 bez duplikovania hodnôt na každom produkte.
     */
    public function up(): void
    {
        Schema::create('attributes', function (Blueprint $table) {
            $table->id();
            $table->string('status', 32)->default('active')->index();
            $table->string('code', 64)->unique();
            $table->string('name', 128);
            $table->string('unit', 16)->nullable();
            $table->enum('input_type', ['select', 'color', 'text'])->default('select');

            // is_variant: hodnota rozlišuje skladovú položku (vlastná cena, sklad, EAN).
            // Nevariantné atribúty sú čisto informačné / filtrovacie.
            $table->boolean('is_variant')->default(true);
            $table->boolean('is_filterable')->default(true);
            $table->boolean('is_public')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attributes');
    }
};
