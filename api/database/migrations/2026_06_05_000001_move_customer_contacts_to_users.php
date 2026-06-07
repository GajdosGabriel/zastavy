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
            if (! Schema::hasColumn('users', 'username')) {
                $table->string('username', 100)->nullable()->after('slug');
            }

            if (! Schema::hasColumn('users', 'phone')) {
                $table->string('phone', 40)->nullable()->after('email');
            }
        });

        Schema::table('orders', function (Blueprint $table) {
            if (! Schema::hasColumn('orders', 'user_id')) {
                $table->unsignedBigInteger('user_id')->nullable()->after('customer_id');
            }
        });

        if (Schema::hasColumn('orders', 'user_id') && ! $this->hasForeignKey('orders', 'orders_user_id_foreign')) {
            DB::statement(sprintf(
                'ALTER TABLE `orders` MODIFY `user_id` %s NULL',
                $this->foreignKeyColumnType('users', 'id')
            ));
        }

        $passwordHash = Hash::make(Str::random(32));

        DB::table('customers')
            ->whereNotNull('email')
            ->orderBy('id')
            ->get()
            ->each(function ($customer) use ($passwordHash) {
                $username = $customer->username ?: $customer->name;
                $nameParts = preg_split('/\s+/', trim((string) $username), 2);
                $firstName = $nameParts[0] ?: (string) $customer->company;
                $lastName = $nameParts[1] ?? '';

                $userId = DB::table('users')->where('email', $customer->email)->value('id');

                if (! $userId) {
                    $userId = DB::table('users')->insertGetId($this->userData([
                        'name' => $username ?: $customer->company ?: 'Kontakt',
                        'firstName' => $firstName ?: 'Kontakt',
                        'lastName' => $lastName,
                        'slug' => Str::slug($username ?: $customer->company ?: 'kontakt-' . $customer->id),
                        'username' => $username,
                        'email' => $customer->email,
                        'phone' => $customer->phone,
                        'customer_id' => $customer->id,
                        'password' => $passwordHash,
                        'created_at' => $customer->created_at,
                        'updated_at' => $customer->updated_at ?: $customer->created_at,
                    ]));
                }

                DB::table('orders')
                    ->where('customer_id', $customer->id)
                    ->whereNull('user_id')
                    ->update(['user_id' => $userId]);
            });

        DB::table('orders')
            ->leftJoin('users', 'orders.user_id', '=', 'users.id')
            ->whereNotNull('orders.user_id')
            ->whereNull('users.id')
            ->update(['orders.user_id' => null]);

        Schema::table('orders', function (Blueprint $table) {
            if (! $this->hasForeignKey('orders', 'orders_user_id_foreign')) {
                $table->foreign('user_id')->references('id')->on('users')->onDelete('set null');
            }
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
        return DB::table('information_schema.TABLE_CONSTRAINTS')
            ->where('CONSTRAINT_SCHEMA', DB::getDatabaseName())
            ->where('TABLE_NAME', $table)
            ->where('CONSTRAINT_NAME', $foreignKey)
            ->where('CONSTRAINT_TYPE', 'FOREIGN KEY')
            ->exists();
    }

    private function foreignKeyColumnType(string $table, string $column): string
    {
        $columnType = DB::table('information_schema.COLUMNS')
            ->where('TABLE_SCHEMA', DB::getDatabaseName())
            ->where('TABLE_NAME', $table)
            ->where('COLUMN_NAME', $column)
            ->value('COLUMN_TYPE');

        return strtoupper((string) $columnType ?: 'BIGINT UNSIGNED');
    }

    private function userData(array $data): array
    {
        return array_filter(
            $data,
            fn (string $column) => Schema::hasColumn('users', $column),
            ARRAY_FILTER_USE_KEY
        );
    }
};
