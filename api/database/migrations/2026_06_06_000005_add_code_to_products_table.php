<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            if (! Schema::hasColumn('products', 'code')) {
                $table->string('code', 64)->nullable()->after('id');
            }
        });

        DB::table('products')
            ->whereNull('code')
            ->orderBy('id')
            ->get(['id'])
            ->each(function ($product) {
                DB::table('products')
                    ->where('id', $product->id)
                    ->update(['code' => sprintf('TOV-%06d', $product->id)]);
            });

        DB::statement('ALTER TABLE `products` MODIFY `code` VARCHAR(64) NOT NULL');

        Schema::table('products', function (Blueprint $table) {
            if (! $this->hasIndex('products', 'products_code_unique')) {
                $table->unique('code');
            }
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            if ($this->hasIndex('products', 'products_code_unique')) {
                $table->dropUnique('products_code_unique');
            }

            if (Schema::hasColumn('products', 'code')) {
                $table->dropColumn('code');
            }
        });
    }

    private function hasIndex(string $table, string $index): bool
    {
        $database = DB::getDatabaseName();

        return DB::table('information_schema.STATISTICS')
            ->where('TABLE_SCHEMA', $database)
            ->where('TABLE_NAME', $table)
            ->where('INDEX_NAME', $index)
            ->exists();
    }
};
