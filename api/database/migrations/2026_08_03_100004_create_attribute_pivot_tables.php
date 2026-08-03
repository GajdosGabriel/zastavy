<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Kombinácia hodnôt, ktorá definuje variant. Zdroj pravdy.
        Schema::create('attribute_value_product_variant', function (Blueprint $table) {
            $table->foreignId('product_variant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('attribute_value_id')->constrained()->cascadeOnDelete();

            $table->primary(['product_variant_id', 'attribute_value_id'], 'avpv_primary');
            $table->index('attribute_value_id', 'avpv_value_index');
        });

        // Fasetový index na úrovni produktu. Odvodené riadky (is_variant_option = 1)
        // sa prepočítavajú z variantov; ručne priradené hodnoty prežijú prepočet.
        Schema::create('attribute_value_product', function (Blueprint $table) {
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('attribute_value_id')->constrained()->cascadeOnDelete();
            $table->boolean('is_variant_option')->default(false);

            $table->primary(['product_id', 'attribute_value_id'], 'avp_primary');
            $table->index('attribute_value_id', 'avp_value_index');
        });

        // Ktoré atribúty produkt používa — drží poradie v admin editore.
        Schema::create('attribute_product', function (Blueprint $table) {
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('attribute_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('sort_order')->default(0);

            $table->primary(['product_id', 'attribute_id'], 'ap_primary');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attribute_product');
        Schema::dropIfExists('attribute_value_product');
        Schema::dropIfExists('attribute_value_product_variant');
    }
};
