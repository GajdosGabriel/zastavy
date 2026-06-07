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
        Schema::create('stocks', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('order_id');
            $table->unsignedBigInteger('shipping_id');
            $table->unsignedBigInteger('order_product_id');
            $table->integer('quantity');
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('order_id')->references('id')->on('orders')->onDelete('cascade');
            $table->foreign('shipping_id')->references('id')->on('shippings')->onDelete('cascade');
            // $table->foreign('order_product_id')->references('id')->on('order_items')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('stocks');
    }
};
