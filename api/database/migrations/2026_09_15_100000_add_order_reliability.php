<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::table('orders')->whereNotNull('serial_number')->select('serial_number')->groupBy('serial_number')->havingRaw('COUNT(*) > 1')->exists()) {
            throw new RuntimeException('Duplicitné čísla objednávok: pred migráciou ich skontrolujte a opravte.');
        }
        Schema::table('orders', function (Blueprint $table) {
            $table->json('billing_snapshot')->nullable();
            $table->string('snapshot_source', 20)->nullable();
            $table->string('shipping_method_name')->nullable();
            $table->string('payment_method_name')->nullable();
            $table->unique('serial_number');
        });
        Schema::table('order_products', fn (Blueprint $table) => $table->json('product_snapshot')->nullable());
        Schema::create('order_sequences', function (Blueprint $table) {
            $table->string('period', 7)->primary();
            $table->unsignedBigInteger('last_number')->default(0);
        });
        Schema::create('checkout_submissions', function (Blueprint $table) {
            $table->uuid('key')->primary();
            $table->string('actor_scope', 80);
            $table->string('request_hash', 64);
            $table->unsignedBigInteger('order_id')->nullable();
            $table->timestamps();
        });
        DB::table('orders')->whereNotNull('serial_number')->orderBy('id')->chunkById(500, function ($orders) {
            foreach ($orders as $order) {
                if (preg_match('/^(\d{4}-\d{2})-(\d+)$/', $order->serial_number, $match)) {
                    DB::table('order_sequences')->insertOrIgnore(['period' => $match[1], 'last_number' => 0]);
                    DB::table('order_sequences')->where('period', $match[1])->where('last_number', '<', (int) $match[2])->update(['last_number' => (int) $match[2]]);
                }
            }
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('checkout_submissions');
        Schema::dropIfExists('order_sequences');
        Schema::table('order_products', fn (Blueprint $table) => $table->dropColumn('product_snapshot'));
        Schema::table('orders', function (Blueprint $table) {
            $table->dropUnique(['serial_number']);
            $table->dropColumn(['billing_snapshot', 'snapshot_source', 'shipping_method_name', 'payment_method_name']);
        });
    }
};
