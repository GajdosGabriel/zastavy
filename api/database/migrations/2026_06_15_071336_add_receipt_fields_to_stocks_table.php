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
        Schema::table('stocks', function (Blueprint $table) {
            $table->unsignedBigInteger('order_id')->nullable()->change();
            $table->unsignedBigInteger('order_product_id')->nullable()->change();
            $table->unsignedBigInteger('product_id')->nullable()->after('order_return_id');
            $table->decimal('price', 10, 2)->nullable()->after('quantity');
            $table->string('note')->nullable()->after('price');

            $table->foreign('product_id')->references('id')->on('products')->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::table('stocks', function (Blueprint $table) {
            $table->dropForeign(['product_id']);
            $table->dropColumn(['product_id', 'price', 'note']);
            $table->unsignedBigInteger('order_id')->nullable(false)->change();
            $table->unsignedBigInteger('order_product_id')->nullable(false)->change();
        });
    }
};
