<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('roles', 'description')) {
            DB::statement('ALTER TABLE roles MODIFY description TEXT NULL');
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('roles', 'description')) {
            DB::statement('ALTER TABLE roles MODIFY description TEXT NOT NULL');
        }
    }
};
