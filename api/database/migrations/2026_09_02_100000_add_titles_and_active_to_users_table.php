<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Titul pred menom (Ing., Mgr., …) a za menom (PhD., MBA, …).
            $table->string('prefix', 40)->nullable()->after('uuid');
            $table->string('postfix', 40)->nullable()->after('prefix');
            // Rýchly vypínač prístupu — nezávislý od životného cyklu účtu (status).
            $table->boolean('active')->default(true)->index()->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['active']);
            $table->dropColumn(['prefix', 'postfix', 'active']);
        });
    }
};
