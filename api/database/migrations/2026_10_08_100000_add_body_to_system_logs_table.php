<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Telo odoslaného e-mailu (HTML) do denníka — aby bolo v /admin/dennik vidno,
 * čo presne príjemcovi odišlo. Zapisuje App\Listeners\SystemLogSubscriber.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('system_logs') || Schema::hasColumn('system_logs', 'body')) {
            return;
        }

        Schema::table('system_logs', function (Blueprint $table) {
            $table->mediumText('body')->nullable()->after('context');
        });
    }

    public function down(): void
    {
        if (Schema::hasColumn('system_logs', 'body')) {
            Schema::table('system_logs', function (Blueprint $table) {
                $table->dropColumn('body');
            });
        }
    }
};
