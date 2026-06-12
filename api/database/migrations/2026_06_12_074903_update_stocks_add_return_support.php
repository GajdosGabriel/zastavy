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
            $table->foreignId('order_return_id')->nullable()->after('shipping_id')->constrained('order_returns')->nullOnDelete();
            $table->unsignedBigInteger('shipping_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('stocks', function (Blueprint $table) {
            $table->dropForeign(['order_return_id']);
            $table->dropColumn('order_return_id');
            $table->unsignedBigInteger('shipping_id')->nullable(false)->change();
        });
    }
};
