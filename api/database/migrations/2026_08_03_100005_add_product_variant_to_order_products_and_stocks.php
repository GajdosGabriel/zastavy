<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('order_products', function (Blueprint $table) {
            $table->unsignedBigInteger('product_variant_id')->nullable()->after('product_id');

            // Snapshot popisu variantu — objednávka musí zostať čitateľná
            // aj keď sa variant neskôr premenuje alebo zmaže.
            $table->string('variant_label', 255)->nullable()->after('product_variant_id');

            $table->foreign('product_variant_id')
                ->references('id')->on('product_variants')
                ->nullOnDelete();
        });

        Schema::table('stocks', function (Blueprint $table) {
            $table->unsignedBigInteger('product_variant_id')->nullable()->after('product_id');

            $table->foreign('product_variant_id')
                ->references('id')->on('product_variants')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('stocks', function (Blueprint $table) {
            $table->dropForeign(['product_variant_id']);
            $table->dropColumn('product_variant_id');
        });

        Schema::table('order_products', function (Blueprint $table) {
            $table->dropForeign(['product_variant_id']);
            $table->dropColumn(['product_variant_id', 'variant_label']);
        });
    }
};
