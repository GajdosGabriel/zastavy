<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stocks', function (Blueprint $table) {
            $table->id();
            $table->string('status', 32)->default('active')->index();
            $table->unsignedBigInteger('order_id');
            $table->unsignedBigInteger('shipping_id')->nullable();
            $table->unsignedBigInteger('order_return_id')->nullable();
            $table->unsignedBigInteger('order_product_id');
            $table->integer('quantity');
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('order_id')->references('id')->on('orders')->onDelete('cascade');
            $table->foreign('shipping_id')->references('id')->on('shippings')->onDelete('cascade');
            $table->foreign('order_return_id')->references('id')->on('order_returns')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stocks');
    }
};
