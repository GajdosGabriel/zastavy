<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\PersonalAccessToken;
use Tests\TestCase;

class StoragePruneTest extends TestCase
{
    use RefreshDatabase;

    public function test_expired_cache_rows_and_locks_are_deleted(): void
    {
        DB::table('cache')->insert([
            ['key' => 'old-throttle', 'value' => 'i:1;', 'expiration' => time() - 60],
            ['key' => 'live', 'value' => 'i:1;', 'expiration' => time() + 3600],
        ]);
        DB::table('cache_locks')->insert([
            ['key' => 'old-lock', 'owner' => 'x', 'expiration' => time() - 60],
            ['key' => 'live-lock', 'owner' => 'y', 'expiration' => time() + 3600],
        ]);

        $this->artisan('cache:prune-expired')->assertExitCode(0);

        $this->assertSame(['live'], DB::table('cache')->pluck('key')->all());
        $this->assertSame(['live-lock'], DB::table('cache_locks')->pluck('key')->all());
    }

    public function test_tokens_older_than_cookie_lifetime_are_rejected_and_pruned(): void
    {
        $user = User::factory()->create();
        $old = $user->createToken('API Token');
        PersonalAccessToken::whereKey($old->accessToken->id)->update(['created_at' => now()->subDays(32)]);
        $fresh = $user->createToken('API Token');

        $this->withToken($old->plainTextToken)->postJson('/api/logout')->assertUnauthorized();

        $this->artisan('sanctum:prune-expired', ['--hours' => 24])->assertExitCode(0);

        $this->assertSame([$fresh->accessToken->id], PersonalAccessToken::pluck('id')->all());
    }
}
