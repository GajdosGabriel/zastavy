<?php

namespace Tests\Feature;

use App\Models\SystemLog;
use App\Models\User;
use App\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SystemLogMailBodyTest extends TestCase
{
    use RefreshDatabase;

    public function test_sent_mail_body_is_stored_and_shown_only_in_detail(): void
    {
        Mail::html('<p>Dobrý deň, objednávka je na ceste.</p>', function ($message) {
            $message->to('buyer@example.test')->subject('Skúšobný e-mail');
        });

        $log = SystemLog::where('event', 'mail.sent')->sole();
        $this->assertStringContainsString('objednávka je na ceste', $log->body);
        $this->assertNotEmpty($log->context['from']);

        $admin = User::factory()->create();
        $admin->assignRole('super-admin');
        Sanctum::actingAs($admin);

        $list = $this->getJson('/api/admin/system-logs?channel=mail')->assertOk();
        $list->assertJsonPath('data.0.hasBody', true);
        $this->assertArrayNotHasKey('body', $list->json('data.0'));

        $this->getJson('/api/admin/system-logs/'.$log->id)
            ->assertOk()
            ->assertJsonPath('data.message', 'Skúšobný e-mail')
            ->assertJsonPath('data.recipient', 'buyer@example.test')
            ->assertJsonPath('data.body', $log->body);
    }

    public function test_password_and_bulk_mail_bodies_are_not_stored(): void
    {
        User::factory()->create(['email' => 'reset@example.test'])->notify(new ResetPassword('secret-token'));

        Mail::html('<p>Kampaň</p>', function ($message) {
            $message->to('contact@example.test')->subject('Kampaň');
            $message->getSymfonyMessage()->getHeaders()->addTextHeader('List-Unsubscribe', '<https://example.test/u>');
        });

        $reset = SystemLog::where('event', 'mail.sent')->where('recipient', 'reset@example.test')->sole();
        $this->assertNull($reset->body);
        $this->assertSame('sensitive', $reset->context['body_omitted']);

        $bulk = SystemLog::where('event', 'mail.sent')->where('recipient', 'contact@example.test')->sole();
        $this->assertNull($bulk->body);
        $this->assertSame('bulk', $bulk->context['body_omitted']);
    }

    public function test_detail_is_forbidden_for_regular_user(): void
    {
        $log = SystemLog::create(['channel' => 'mail', 'event' => 'mail.sent', 'message' => 'x', 'body' => '<p>x</p>']);
        Sanctum::actingAs(User::factory()->create());

        $this->getJson('/api/admin/system-logs/'.$log->id)->assertForbidden();
    }
}
