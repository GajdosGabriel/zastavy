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
                $table->unsignedBigInteger('user_id')->nullable()->after('customer_id');
                $table->foreign('user_id')->references('id')->on('users')->onDelete('set null');
            }
        });

        DB::table('customers')
            ->whereNotNull('email')
            ->orderBy('id')
            ->chunkById(100, function ($customers) {
                foreach ($customers as $customer) {
                    $username = $customer->username ?: $customer->name;
                    $nameParts = preg_split('/\s+/', trim((string) $username), 2);
                    $firstName = $nameParts[0] ?: (string) $customer->company;
                    $lastName = $nameParts[1] ?? '';

                    $userId = DB::table('users')->insertGetId([
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

                    DB::table('orders')
                        ->where('customer_id', $customer->id)
                        ->whereNull('user_id')
                        ->update(['user_id' => $userId]);
                }
            });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            if (Schema::hasColumn('orders', 'user_id')) {
                $table->dropForeign(['user_id']);
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
};
