<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stocks', fn (Blueprint $table) => $table->decimal('price', 14, 6)->nullable()->change());
    }

    public function down(): void
    {
        Schema::table('stocks', fn (Blueprint $table) => $table->decimal('price', 10, 2)->nullable()->change());
    }
};
