<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Cena, sklad, váha a voľný reťazec attributes sa presunuli na product_variants.
     * Na produkte zostáva len to, čo je spoločné pre celú kartu: DPH, jednotka, obsah.
     */
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn([
                'price',
                'sale_price',
                'discount',
                'quantity',
                'weight',
                'attributes',
                'min_order',
            ]);
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->unsignedInteger('quantity')->nullable();
            $table->decimal('weight', 8, 2)->nullable();
            $table->decimal('price', 8, 2)->nullable();
            $table->decimal('sale_price', 8, 2)->nullable();
            $table->decimal('discount', 8, 2)->nullable();
            $table->string('attributes', 255)->nullable();
            $table->integer('min_order')->default(1);
        });
    }
};
