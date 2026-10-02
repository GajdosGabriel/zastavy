<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    // Testovací oznam zo seedu prvej migrácie sa zobrazoval zákazníkom pod hlavičkou.
    public function up(): void
    {
        DB::table('announcements')
            ->where('placement', 'bottom')
            ->where('title', 'Nav Bar Bottom component')
            ->whereNull('deleted_at')
            ->update(['deleted_at' => now()]);
    }

    public function down(): void
    {
        DB::table('announcements')
            ->where('placement', 'bottom')
            ->where('title', 'Nav Bar Bottom component')
            ->update(['deleted_at' => null]);
    }
};
