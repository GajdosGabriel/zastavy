<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->increments('id');
            $table->string('name', 255);
            $table->string('slug', 255);
            $table->text('description')->nullable();
            $table->unsignedInteger('quantity')->nullable();
            $table->decimal('weight', 8, 2)->nullable();
            $table->decimal('price', 8, 2)->nullable();
            $table->decimal('sale_price', 8, 2)->nullable();
            $table->decimal('discount', 8, 2)->nullable();
            $table->tinyInteger('vat'); // Opravený zápis
            $table->unsignedInteger('image_id')->nullable();
            $table->boolean('featured')->default(false); // Opravený boolean
            $table->boolean('published')->default(true); // Opravený boolean
            $table->string('attributes', 255)->nullable();
            $table->enum('unit_value', ['ks', 'l', 'kg'])->default('ks');
            $table->integer('min_order')->default(1);
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
