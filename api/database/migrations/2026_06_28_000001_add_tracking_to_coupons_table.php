<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('coupons', function (Blueprint $table) {
            if (! Schema::hasColumn('coupons', 'email')) {
                $table->string('email')->nullable()->after('active');
            }
            if (! Schema::hasColumn('coupons', 'source_order_id')) {
                $table->unsignedBigInteger('source_order_id')->nullable()->after('email');
            }
        });
    }

    public function down(): void
    {
        Schema::table('coupons', function (Blueprint $table) {
            $table->dropColumn(['email', 'source_order_id']);
        });
    }
};
