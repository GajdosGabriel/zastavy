<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CookieAuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_sets_http_only_cookie_and_hides_token_from_body(): void
    {
        $user = User::factory()->create(['password' => bcrypt('tajne-heslo-1')]);

        $response = $this->postJson('/api/login', ['email' => $user->email, 'password' => 'tajne-heslo-1']);

        $response->assertOk()->assertJsonMissingPath('token');
        $cookie = collect($response->headers->getCookies())->firstWhere(fn ($c) => $c->getName() === 'auth_token');
        $this->assertNotNull($cookie);
        $this->assertTrue($cookie->isHttpOnly());

        $this->app['auth']->forgetGuards(); // testovacia aplikácia drží guard z prvého requestu

        $this->call('GET', '/api/user', [], ['auth_token' => $cookie->getValue()], [], ['HTTP_ACCEPT' => 'application/json'])
            ->assertOk()
            ->assertJsonPath('data.isAuth', true);
    }

    public function test_unauthenticated_request_gets_json_401(): void
    {
        $this->postJson('/api/logout')->assertUnauthorized()->assertJsonMissingPath('trace');
    }
}
