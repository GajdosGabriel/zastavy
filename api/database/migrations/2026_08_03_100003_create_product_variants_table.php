<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Variant = skladová položka. Cena, zľava, sklad, váha a EAN patria sem,
     * nie na produkt — produkt je len marketingový obal nad variantmi.
     */
    public function up(): void
    {
        Schema::create('product_variants', function (Blueprint $table) {
            $table->id();
            $table->string('status', 32)->default('active')->index();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->string('code', 100)->unique();
            $table->string('ean', 32)->nullable()->index();

            // Odvodený popis kombinácie ("100 × 150 cm / Polyester") — držíme
            // denormalizovane, aby sa dal zobraziť bez načítania pivotu.
            $table->string('name', 255)->nullable();

            $table->decimal('price', 8, 2);
            $table->decimal('sale_price', 8, 2)->nullable();
            $table->decimal('discount', 8, 2)->nullable();
            $table->unsignedInteger('quantity')->nullable();
            $table->decimal('weight', 8, 2)->nullable();
            $table->unsignedInteger('min_order')->default(1);
            $table->unsignedBigInteger('image_id')->nullable();
            $table->boolean('is_default')->default(false);
            $table->boolean('published')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['product_id', 'published']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_variants');
    }
};
