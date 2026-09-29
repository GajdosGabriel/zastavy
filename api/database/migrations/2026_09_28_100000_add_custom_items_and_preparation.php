<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('order_products', function (Blueprint $table) {
            $table->unsignedBigInteger('product_id')->nullable()->change();
            $table->boolean('is_custom')->default(false);
        });
        Schema::table('orders', fn (Blueprint $table) => $table->json('price_adjustment')->nullable());
        Schema::table('shippings', function (Blueprint $table) {
            $table->json('prepared_items')->nullable();
            $table->timestamp('dispatched_at')->nullable();
        });
        DB::table('shippings')->update(['dispatched_at' => DB::raw('created_at')]);
    }

    public function down(): void
    {
        Schema::table('shippings', fn (Blueprint $table) => $table->dropColumn(['prepared_items', 'dispatched_at']));
        Schema::table('orders', fn (Blueprint $table) => $table->dropColumn('price_adjustment'));
        Schema::table('order_products', fn (Blueprint $table) => $table->dropColumn('is_custom'));
    }
};
