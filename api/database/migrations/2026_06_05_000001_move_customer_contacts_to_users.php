<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (!Schema::hasColumn('users', 'username')) {
                $table->string('username', 100)->nullable()->after('slug');
            }

            if (!Schema::hasColumn('users', 'phone')) {
                $table->string('phone', 40)->nullable()->after('email');
            }
        });

        Schema::table('orders', function (Blueprint $table) {
            if (!Schema::hasColumn('orders', 'user_id')) {
                $table->unsignedInteger('user_id')->nullable()->after('customer_id');
            }
        });

        DB::statement('ALTER TABLE `orders` MODIFY `user_id` INT UNSIGNED NULL');

        Schema::table('orders', function (Blueprint $table) {
            if (! $this->hasForeignKey('orders', 'orders_user_id_foreign')) {
                $table->foreign('user_id')->references('id')->on('users')->onDelete('set null');
            }
        });

        DB::table('customers')
            ->whereNotNull('email')
            ->orderBy('id')
            ->get()
            ->each(function ($customer) {
                $username = $customer->username ?: $customer->name;
                $nameParts = preg_split('/\s+/', trim((string) $username), 2);
                $firstName = $nameParts[0] ?: (string) $customer->company;
                $lastName = $nameParts[1] ?? '';

                $userId = DB::table('users')->where('email', $customer->email)->value('id');

                if ($userId) {
                    DB::table('users')
                        ->where('id', $userId)
                        ->whereNull('customer_id')
                        ->update([
                            'customer_id' => $customer->id,
                            'updated_at' => now(),
                        ]);
                } else {
                    $userId = DB::table('users')->insertGetId([
                        'name' => $username ?: $customer->company ?: 'Kontakt',
                        'firstName' => $firstName ?: 'Kontakt',
                        'lastName' => $lastName,
                        'slug' => Str::slug($username ?: $customer->company ?: 'kontakt-' . $customer->id),
                        'username' => $username,
                        'email' => $customer->email,
                        'phone' => $customer->phone,
                        'customer_id' => $customer->id,
                        'password' => Hash::make(Str::random(32)),
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }

                DB::table('orders')
                    ->where('customer_id', $customer->id)
                    ->whereNull('user_id')
                    ->update(['user_id' => $userId]);
            });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            if (Schema::hasColumn('orders', 'user_id')) {
                if ($this->hasForeignKey('orders', 'orders_user_id_foreign')) {
                    $table->dropForeign(['user_id']);
                }

                $table->dropColumn('user_id');
            }
        });

        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'phone')) {
                $table->dropColumn('phone');
            }

            if (Schema::hasColumn('users', 'username')) {
                $table->dropColumn('username');
            }
        });
    }

    private function hasForeignKey(string $table, string $foreignKey): bool
    {
        $database = DB::getDatabaseName();

        return DB::table('information_schema.TABLE_CONSTRAINTS')
            ->where('CONSTRAINT_SCHEMA', $database)
            ->where('TABLE_NAME', $table)
            ->where('CONSTRAINT_NAME', $foreignKey)
            ->where('CONSTRAINT_TYPE', 'FOREIGN KEY')
            ->exists();
    }
};
