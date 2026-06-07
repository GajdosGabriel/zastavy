<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (! Schema::hasColumn('users', 'firstName')) {
                $table->string('firstName')->nullable()->after('name');
            }

            if (! Schema::hasColumn('users', 'lastName')) {
                $table->string('lastName')->nullable()->after('firstName');
            }
        });

        if (Schema::hasColumn('users', 'firstName') && Schema::hasColumn('users', 'lastName')) {
            DB::table('users')
                ->whereNull('firstName')
                ->orWhereNull('lastName')
                ->orderBy('id')
                ->get(['id', 'name', 'username', 'email'])
                ->each(function ($user) {
                    $displayName = trim((string) ($user->name ?: $user->username ?: $user->email));
                    $parts = preg_split('/\s+/', $displayName, 2) ?: [];

                    DB::table('users')
                        ->where('id', $user->id)
                        ->update([
                            'firstName' => $parts[0] ?? 'Kontakt',
                            'lastName' => $parts[1] ?? '',
                        ]);
                });

            DB::statement("UPDATE `users` SET `firstName` = 'Kontakt' WHERE `firstName` IS NULL");
            DB::statement("UPDATE `users` SET `lastName` = '' WHERE `lastName` IS NULL");
            DB::statement('ALTER TABLE `users` MODIFY `firstName` VARCHAR(255) NOT NULL');
            DB::statement('ALTER TABLE `users` MODIFY `lastName` VARCHAR(255) NOT NULL');
        }
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'lastName')) {
                $table->dropColumn('lastName');
            }

            if (Schema::hasColumn('users', 'firstName')) {
                $table->dropColumn('firstName');
            }
        });
    }
};
