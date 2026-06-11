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
        Schema::table('shipping_methods', fn (Blueprint $t) => $t->softDeletes());
        Schema::table('payment_methods',  fn (Blueprint $t) => $t->softDeletes());
        Schema::table('coupons',          fn (Blueprint $t) => $t->softDeletes());
    }

    public function down(): void
    {
        Schema::table('shipping_methods', fn (Blueprint $t) => $t->dropSoftDeletes());
        Schema::table('payment_methods',  fn (Blueprint $t) => $t->dropSoftDeletes());
        Schema::table('coupons',          fn (Blueprint $t) => $t->dropSoftDeletes());
    }
};
