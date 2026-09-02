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
            $table->timestamp('last_login_at')->nullable()->index()->after('email_verified_at');
            $table->string('last_login_ip', 45)->nullable()->after('last_login_at');
            $table->unsignedInteger('login_count')->default(0)->after('last_login_ip');
        });

        // Spätné doplnenie z API tokenov — každé prihlásenie vytvára nový token,
        // takže max(created_at) je posledné prihlásenie a počet tokenov ~ počet prihlásení.
        if (! Schema::hasTable('personal_access_tokens')) {
            return;
        }

        $tokens = DB::table('personal_access_tokens')
            ->where('tokenable_type', 'App\Models\User')
            ->groupBy('tokenable_id')
            ->get([
                'tokenable_id',
                DB::raw('MAX(created_at) as last_login_at'),
                DB::raw('COUNT(*) as login_count'),
            ]);

        foreach ($tokens as $row) {
            DB::table('users')
                ->where('id', $row->tokenable_id)
                ->update([
                    'last_login_at' => $row->last_login_at,
                    'login_count'   => $row->login_count,
                ]);
        }
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['last_login_at']);
            $table->dropColumn(['last_login_at', 'last_login_ip', 'login_count']);
        });
    }
};
