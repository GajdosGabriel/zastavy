<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (! Schema::hasColumn('users', 'uuid')) {
                $table->uuid('uuid')->nullable()->after('id');
            }
        });

        Schema::table('orders', function (Blueprint $table) {
            if (! Schema::hasColumn('orders', 'uuid')) {
                $table->uuid('uuid')->nullable()->after('id');
            }
        });

        $this->fillMissingUuids('users');
        $this->fillMissingUuids('orders');

        DB::statement('ALTER TABLE `users` MODIFY `uuid` CHAR(36) NOT NULL');
        DB::statement('ALTER TABLE `orders` MODIFY `uuid` CHAR(36) NOT NULL');

        Schema::table('users', function (Blueprint $table) {
            if (! $this->hasIndex('users', 'users_uuid_unique')) {
                $table->unique('uuid');
            }
        });

        Schema::table('orders', function (Blueprint $table) {
            if (! $this->hasIndex('orders', 'orders_uuid_unique')) {
                $table->unique('uuid');
            }
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            if ($this->hasIndex('orders', 'orders_uuid_unique')) {
                $table->dropUnique('orders_uuid_unique');
            }

            if (Schema::hasColumn('orders', 'uuid')) {
                $table->dropColumn('uuid');
            }
        });

        Schema::table('users', function (Blueprint $table) {
            if ($this->hasIndex('users', 'users_uuid_unique')) {
                $table->dropUnique('users_uuid_unique');
            }

            if (Schema::hasColumn('users', 'uuid')) {
                $table->dropColumn('uuid');
            }
        });
    }

    private function fillMissingUuids(string $table): void
    {
        DB::table($table)
            ->whereNull('uuid')
            ->orderBy('id')
            ->pluck('id')
            ->each(function ($id) use ($table) {
                DB::table($table)
                    ->where('id', $id)
                    ->update(['uuid' => (string) Str::uuid()]);
            });
    }

    private function hasIndex(string $table, string $index): bool
    {
        return DB::table('information_schema.STATISTICS')
            ->where('TABLE_SCHEMA', DB::getDatabaseName())
            ->where('TABLE_NAME', $table)
            ->where('INDEX_NAME', $index)
            ->exists();
    }
};
