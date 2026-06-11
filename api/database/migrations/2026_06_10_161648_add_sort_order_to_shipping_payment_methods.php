<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shipping_methods', function (Blueprint $table) {
            $table->unsignedSmallInteger('sort_order')->default(99)->after('active');
        });
        Schema::table('payment_methods', function (Blueprint $table) {
            $table->unsignedSmallInteger('sort_order')->default(99)->after('active');
        });
    }

    public function down(): void
    {
        Schema::table('shipping_methods', function (Blueprint $table) {
            $table->dropColumn('sort_order');
        });
        Schema::table('payment_methods', function (Blueprint $table) {
            $table->dropColumn('sort_order');
        });
    }
};
