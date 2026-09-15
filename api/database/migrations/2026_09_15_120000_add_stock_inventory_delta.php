<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('product_variants', fn (Blueprint $table) => $table->bigInteger('quantity')->nullable()->change());
        Schema::table('order_returns', fn (Blueprint $table) => $table->boolean('restocked')->nullable());
        Schema::table('shippings', function (Blueprint $table) {
            $table->uuid('submission_key')->nullable();
            $table->string('submission_hash', 64)->nullable();
            $table->unique(['order_id', 'submission_key']);
        });
        Schema::table('stocks', fn (Blueprint $table) => $table->integer('inventory_delta')->nullable());
    }

    public function down(): void
    {
        if (DB::table('product_variants')->where('quantity', '<', 0)->exists()) {
            throw new RuntimeException('Záporné zásoby treba pred rollbackom vyrovnať inventúrou.');
        }
        Schema::table('order_returns', fn (Blueprint $table) => $table->dropColumn('restocked'));
        Schema::table('product_variants', fn (Blueprint $table) => $table->unsignedInteger('quantity')->nullable()->change());
        Schema::table('shippings', function (Blueprint $table) {
            $table->dropUnique(['order_id', 'submission_key']);
            $table->dropColumn(['submission_key', 'submission_hash']);
        });
        Schema::table('stocks', fn (Blueprint $table) => $table->dropColumn('inventory_delta'));
    }
};
