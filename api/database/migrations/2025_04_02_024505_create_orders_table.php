<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('status', 32)->default('draft')->index();
            $table->unsignedBigInteger('customer_id');
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('name', 150)->nullable();
            $table->string('email', 150)->nullable();
            $table->string('phone', 40)->nullable();
            $table->text('note')->nullable();
            $table->boolean('wants_coupon')->default(false);
            $table->string('serial_number', 255)->nullable();
            $table->boolean('isOpened')->default(false);
            $table->boolean('isDelivered')->nullable();
            $table->timestamps();
            $table->softDeletes();

            // Checkout FK columns — nullable so no ordering dependency
            $table->unsignedBigInteger('shipping_method_id')->nullable();
            $table->decimal('shipping_price', 8, 2)->nullable();
            $table->unsignedBigInteger('payment_method_id')->nullable();
            $table->decimal('payment_fee', 8, 2)->nullable();
            $table->unsignedBigInteger('coupon_id')->nullable();
            $table->decimal('discount_amount', 8, 2)->nullable();

            $table->foreign('customer_id')->references('id')->on('customers')->onDelete('cascade');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
