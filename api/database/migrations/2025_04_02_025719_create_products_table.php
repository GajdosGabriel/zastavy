<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->string('status', 32)->default('active')->index();
            $table->string('code', 100)->nullable()->index();
            $table->string('name', 255);
            $table->string('slug', 255);
            $table->text('description')->nullable();
            $table->unsignedInteger('quantity')->nullable();
            $table->decimal('weight', 8, 2)->nullable();
            $table->decimal('price', 8, 2)->nullable();
            $table->decimal('sale_price', 8, 2)->nullable();
            $table->decimal('discount', 8, 2)->nullable();
            $table->tinyInteger('vat');
            $table->unsignedInteger('image_id')->nullable();
            $table->boolean('featured')->default(false);
            $table->boolean('published')->default(true);
            $table->string('attributes', 255)->nullable();
            $table->enum('unit_value', ['ks', 'l', 'kg'])->default('ks');
            $table->integer('min_order')->default(1);
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
