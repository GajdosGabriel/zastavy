<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\EmailingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class EmailingTest extends TestCase
{
    use RefreshDatabase;

    public function createApplication()
    {
        $app = parent::createApplication();
        if ($app['db']->connection()->getDriverName() === 'sqlite') {
            $pdo = $app['db']->connection()->getPdo();
            $pdo->sqliteCreateFunction('REGEXP', fn ($pattern, $value) => preg_match('~'.$pattern.'~', $value ?? ''));
            $pdo->sqliteCreateFunction('CHAR_LENGTH', fn ($s) => mb_strlen($s));
            $pdo->sqliteCreateFunction('CONCAT', fn (...$parts) => implode('', $parts));
            $pdo->sqliteCreateFunction('SUBSTRING', fn ($s, $start, $length) => mb_substr($s, $start - 1, $length));
        }

        return $app;
    }

    private function admin(): void
    {
        $user = User::factory()->create();
        $user->assignRole('super-admin');
        Sanctum::actingAs($user);
        config(['emailing.enabled' => true, 'mail.default' => 'smtp']);
    }

    private function content(): array
    {
        return ['name' => 'Jesenná ponuka', 'subject' => 'Ponuka', 'heading' => 'Vitajte', 'body' => 'Text <script>alert(1)</script>', 'button_url' => 'https://example.test', 'button_label' => 'Pozrieť'];
    }

    private function contact(string $emails = 'customer@example.test'): void
    {
        $this->postJson('/api/emailing/contacts', ['emails' => $emails, 'permission_note' => 'Súhlas z formulára', 'confirmed' => true])->assertOk();
    }

    private function campaign(): int
    {
        return $this->postJson('/api/emailing/campaigns', $this->content())->assertOk()->json('id');
    }

    public function test_measurement_links_and_pixel_are_first_party_and_report_product_engagement(): void
    {
        $this->admin();
        $this->contact();
        $data = $this->content() + ['layout' => 'flags-catalog', 'track_clicks' => true, 'track_opens' => true];
        $id = $this->postJson('/api/emailing/campaigns', $data)->assertOk()->json('id');
        $this->postJson('/api/emailing/preview', $data)->assertOk();
        $this->assertDatabaseCount('mailing_links', 0);
        $this->postJson("/api/emailing/campaigns/$id/queue")->assertOk();
        $html = '';
        Mail::shouldReceive('html')->once()->withArgs(function ($body, $callback) use (&$html) { $html = $body; return true; });
        $this->assertSame(1, app(EmailingService::class)->runBatch());
        $delivery = DB::table('mailing_deliveries')->first();
        $contact = DB::table('mailing_contacts')->first();
        $links = DB::table('mailing_links')->get();
        $this->assertCount(10, $links);
        $this->assertStringContainsString('https://', $html);
        $this->assertStringContainsString('/api/emailing/image/'.$delivery->open_token.'.gif', $html);
        $this->assertStringContainsString('/api/emailing/unsubscribe/'.$contact->token, $html);
        $this->assertStringContainsString('href="https://zastavy-vlajky.sk/"', $html);
        $this->assertStringContainsString('href="mailto:obchod@zastavy-vlajky.sk"', $html);
        $link = $links->first();
        $this->assertStringContainsString('/api/emailing/link/'.$link->token, $html);
        $path = '/api/emailing/link/'.$link->token;
        $this->get($path.'?url=https://evil.example')->assertRedirect($link->url)->assertHeader('Referrer-Policy', 'no-referrer');
        $this->get($path)->assertRedirect($link->url);
        $this->get('/api/emailing/link/'.$links[1]->token)->assertRedirect($links[1]->url);
        $pixel = $this->get('/api/emailing/image/'.$delivery->open_token.'.gif')->assertOk()->assertHeader('Content-Type', 'image/gif');
        $this->assertStringStartsWith('GIF89a', $pixel->getContent());
        $this->assertFalse($pixel->headers->has('Set-Cookie'));
        $this->getJson("/api/emailing/campaigns/$id/deliveries")->assertOk()
            ->assertJsonPath('engagement.clicked', 1)->assertJsonPath('engagement.opened', 1)
            ->assertJsonPath('links.0.url', $link->url)
            ->assertJsonPath('links.0.messages', 1);
        $this->assertDatabaseHas('mailing_links', ['id' => $link->id, 'click_count' => 2]);
        $this->getJson('/api/emailing')->assertOk()->assertJsonPath('stats.clicked', 1)->assertJsonPath('stats.opened', 1);
        $this->get('/api/emailing/link/'.str_repeat('x', 64))->assertNotFound();
        $this->get('/api/emailing/image/'.str_repeat('x', 64).'.gif')->assertOk();
        $this->post('/api/emailing/unsubscribe/'.$contact->token)->assertOk();
        $this->get($path)->assertRedirect($link->url);
        $this->get('/api/emailing/image/'.$delivery->open_token.'.gif')->assertOk();
        $this->assertDatabaseHas('mailing_links', ['id' => $link->id, 'click_count' => 2]);
        $this->assertDatabaseHas('mailing_deliveries', ['id' => $delivery->id, 'open_count' => 1]);
    }

    public function test_measurement_filters_head_scanners_prefetch_and_expires_without_breaking_links(): void
    {
        $this->admin();
        $this->contact();
        $id = $this->postJson('/api/emailing/campaigns', $this->content() + ['track_clicks' => true, 'track_opens' => true])->assertOk()->json('id');
        $this->postJson("/api/emailing/campaigns/$id/queue")->assertOk();
        Mail::shouldReceive('html')->once();
        app(EmailingService::class)->runBatch();
        $link = DB::table('mailing_links')->first();
        $delivery = DB::table('mailing_deliveries')->first();
        $path = '/api/emailing/link/'.$link->token;
        $pixel = '/api/emailing/image/'.$delivery->open_token.'.gif';
        $this->head($path)->assertRedirect($link->url);
        $this->head($pixel)->assertOk();
        $this->get($path, ['User-Agent' => 'SecurityScanner'])->assertRedirect($link->url);
        $this->get($pixel, ['User-Agent' => 'SecurityScanner'])->assertOk();
        $this->get($path, ['Purpose' => 'prefetch'])->assertRedirect($link->url);
        $this->assertDatabaseHas('mailing_links', ['id' => $link->id, 'click_count' => 0]);
        $this->assertDatabaseHas('mailing_deliveries', ['id' => $delivery->id, 'open_count' => 0, 'clicked_at' => null]);
        $this->travel(91)->days();
        $this->get($path)->assertRedirect($link->url);
        $this->get($pixel)->assertOk();
        $this->assertDatabaseHas('mailing_links', ['id' => $link->id, 'click_count' => 0]);
        $this->assertDatabaseHas('mailing_deliveries', ['id' => $delivery->id, 'open_count' => 0]);
    }

    public function test_measurement_is_opt_in_and_test_messages_do_not_track(): void
    {
        $this->admin();
        $this->contact();
        $id = $this->campaign();
        $this->postJson("/api/emailing/campaigns/$id/queue")->assertOk();
        Mail::shouldReceive('html')->twice()->withArgs(function ($html, $callback) {
            $this->assertStringNotContainsString('/emailing/link/', $html);
            $this->assertStringNotContainsString('/emailing/image/', $html);
            return true;
        });
        app(EmailingService::class)->runBatch();
        $this->postJson('/api/emailing/test', $this->content() + ['track_clicks' => true, 'track_opens' => true])->assertOk();
        $this->assertDatabaseCount('mailing_links', 0);
        $this->assertNull(DB::table('mailing_deliveries')->first()->open_token);
        $this->assertFalse(\App\Services\EmailingMeasurement::safeDestination('https://name:secret@example.test'));
        $this->assertFalse(\App\Services\EmailingMeasurement::safeDestination('javascript:alert(1)'));
    }

    public function test_order_trigger_delays_deduplicates_and_stays_active(): void
    {
        $this->admin();
        $this->contact();
        $customer = \App\Models\Customer::create(['company' => 'Buyer', 'email' => 'customer@example.test', 'street' => 'Test 1', 'postcode' => '81101', 'city' => 'Bratislava']);
        \App\Models\Order::create(['customer_id' => $customer->id]);
        $id = $this->postJson('/api/emailing/campaigns', $this->content() + ['trigger_event' => 'order_created', 'delay_hours' => 24])->assertOk()->json('id');
        $this->postJson("/api/emailing/campaigns/$id/queue")->assertOk();
        $this->assertDatabaseCount('mailing_deliveries', 0);
        $order = \App\Models\Order::create(['customer_id' => $customer->id]);
        app(EmailingService::class)->orderCreated($order);
        $this->assertDatabaseCount('mailing_deliveries', 1);
        Mail::shouldReceive('html')->once();
        $this->travel(23)->hours();
        $this->assertSame(0, app(EmailingService::class)->runBatch());
        $this->travel(1)->hours();
        $this->assertSame(1, app(EmailingService::class)->runBatch());
        $this->assertDatabaseHas('mailing_campaigns', ['id' => $id, 'status' => 'queued']);
        \App\Models\Order::create(['customer_id' => $customer->id]);
        $this->assertDatabaseCount('mailing_deliveries', 2);
        $this->postJson("/api/emailing/campaigns/$id/cancel")->assertNoContent();
        \App\Models\Order::create(['customer_id' => $customer->id]);
        $this->assertDatabaseCount('mailing_deliveries', 2);
        $this->travel(24)->hours();
        $this->assertSame(0, app(EmailingService::class)->runBatch());
    }

    public function test_order_trigger_skips_cancelled_orders_and_unsubscribed_contacts(): void
    {
        $this->admin();
        $this->contact();
        $customer = \App\Models\Customer::create(['company' => 'Buyer', 'email' => 'customer@example.test', 'street' => 'Test 1', 'postcode' => '81101', 'city' => 'Bratislava']);
        $id = $this->postJson('/api/emailing/campaigns', $this->content() + ['trigger_event' => 'order_created', 'delay_hours' => 1])->assertOk()->json('id');
        $this->postJson("/api/emailing/campaigns/$id/queue")->assertOk();
        $order = \App\Models\Order::create(['customer_id' => $customer->id]);
        $order->update(['status' => 'cancelled']);
        Mail::shouldReceive('html')->never();
        $this->travel(1)->hours();
        $this->assertSame(1, app(EmailingService::class)->runBatch());
        $this->assertDatabaseHas('mailing_deliveries', ['event_order_id' => $order->id, 'status' => 'skipped']);
        $second = \App\Models\Order::create(['customer_id' => $customer->id]);
        DB::table('mailing_contacts')->update(['unsubscribed_at' => now()]);
        $this->travel(1)->hours();
        $this->assertSame(1, app(EmailingService::class)->runBatch());
        $this->assertDatabaseHas('mailing_deliveries', ['event_order_id' => $second->id, 'status' => 'skipped']);
        \App\Models\Order::create(['customer_id' => $customer->id]);
        $this->assertDatabaseCount('mailing_deliveries', 2);
        $this->postJson('/api/emailing/campaigns', $this->content() + ['trigger_event' => 'order_created', 'delay_hours' => 0])->assertUnprocessable();
    }

    public function test_admin_authorization_and_validation(): void
    {
        $this->getJson('/api/emailing')->assertUnauthorized();
        Sanctum::actingAs(User::factory()->create());
        $this->getJson('/api/emailing')->assertForbidden();
        $this->admin();
        $this->postJson('/api/emailing/contacts', ['emails' => 'a@example.test'])->assertUnprocessable();
        $this->postJson('/api/emailing/campaigns', array_replace($this->content(), ['button_url' => 'javascript:alert(1)']))->assertUnprocessable();
        $this->postJson('/api/emailing/campaigns', array_replace($this->content(), ['subject' => "Subject\r\nBcc: x@example.test"]))->assertUnprocessable();
        $html = $this->postJson('/api/emailing/preview', $this->content())->assertOk()->json('html');
        $this->assertStringNotContainsString('<script>', $html);
        $this->assertStringContainsString('&lt;script&gt;', $html);
    }

    public function test_unsubscribe_is_idempotent_and_import_never_reactivates(): void
    {
        $this->admin();
        $this->contact('CUSTOMER@example.test; customer@example.test');
        $this->assertDatabaseCount('mailing_contacts', 1);
        $c = DB::table('mailing_contacts')->first();
        $url = '/api/emailing/unsubscribe/'.$c->token;
        $this->get($url)->assertOk();
        $this->assertNull(DB::table('mailing_contacts')->first()->unsubscribed_at);
        $this->post($url)->assertOk();
        $this->post($url)->assertOk();
        $this->contact();
        $this->assertDatabaseCount('mailing_contacts', 1);
        $this->assertNotNull(DB::table('mailing_contacts')->first()->unsubscribed_at);
        $this->get('/api/emailing/unsubscribe/'.str_repeat('x', 64))->assertNotFound();
    }

    public function test_mail_client_can_unsubscribe_without_login_or_confirmation(): void
    {
        $token = str_repeat('a', 64);
        DB::table('mailing_contacts')->insert([
            'email' => 'customer@example.test', 'token' => $token,
            'permission_note' => 'Súhlas z formulára', 'permission_at' => now(),
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $url = '/api/emailing/unsubscribe/'.$token;
        $this->get($url)->assertOk();
        $this->assertNull(DB::table('mailing_contacts')->first()->unsubscribed_at);
        $this->post($url, ['List-Unsubscribe' => 'One-Click'])->assertOk();
        $unsubscribedAt = DB::table('mailing_contacts')->first()->unsubscribed_at;
        $this->assertNotNull($unsubscribedAt);
        $this->travel(1)->minutes();
        $this->post($url, ['List-Unsubscribe' => 'One-Click'])->assertOk();
        $this->assertSame($unsubscribedAt, DB::table('mailing_contacts')->first()->unsubscribed_at);
    }

    public function test_queue_snapshot_is_immutable_and_unsubscribe_is_checked_before_send(): void
    {
        $this->admin();
        $this->contact('a@example.test b@example.test');
        $id = $this->campaign();
        $this->postJson("/api/emailing/campaigns/$id/queue")->assertOk()->assertJsonPath('recipients', 2);
        $this->postJson("/api/emailing/campaigns/$id/queue")->assertUnprocessable();
        $this->putJson("/api/emailing/campaigns/$id", $this->content())->assertUnprocessable();
        $this->contact('c@example.test');
        $c = DB::table('mailing_contacts')->where('email', 'b@example.test')->first();
        $this->post('/api/emailing/unsubscribe/'.$c->token)->assertOk();
        Mail::shouldReceive('html')->once()->andReturn(null);
        $this->assertSame(2, app(EmailingService::class)->runBatch());
        $this->assertSame(0, app(EmailingService::class)->runBatch());
        $this->assertDatabaseHas('mailing_deliveries', ['contact_id' => $c->id, 'status' => 'skipped']);
        $this->assertDatabaseCount('mailing_deliveries', 2);
        $this->assertDatabaseHas('mailing_campaigns', ['id' => $id, 'status' => 'completed']);
        $this->getJson('/api/emailing?from='.today()->toDateString().'&to='.today()->toDateString())->assertOk()->assertJsonPath('stats.sent', 1);
    }

    public function test_schedule_limit_cancellation_and_transport_failure(): void
    {
        $this->admin();
        config(['emailing.hourly_limit' => 1]);
        $this->contact('a@example.test b@example.test');
        $id = $this->campaign();
        $this->postJson("/api/emailing/campaigns/$id/queue", ['scheduled_at' => now()->addHour()->toIso8601String()])->assertOk();
        $this->assertSame(0, app(EmailingService::class)->runBatch());
        $this->travel(61)->minutes();
        Mail::shouldReceive('html')->once()->andThrow(new \RuntimeException('SMTP refused'));
        $this->assertSame(1, app(EmailingService::class)->runBatch());
        $this->assertSame(0, app(EmailingService::class)->runBatch());
        $this->assertDatabaseHas('mailing_deliveries', ['campaign_id' => $id, 'status' => 'failed']);
        $this->postJson("/api/emailing/campaigns/$id/cancel")->assertNoContent();
        $this->assertDatabaseHas('mailing_deliveries', ['campaign_id' => $id, 'status' => 'skipped']);
        $this->travel(61)->minutes();
        $this->assertSame(0, app(EmailingService::class)->runBatch());
    }

    public function test_hard_bounce_suppresses_contact_and_soft_bounces_only_after_repeated_failures(): void
    {
        $this->admin();
        $this->contact('gone@example.test full@example.test');
        $id = $this->campaign();
        $this->postJson("/api/emailing/campaigns/$id/queue")->assertOk();
        $messages = [
            'Expected response code "250/251/252" but got code "550", with message "550 5.1.1 User unknown".',
            'Expected response code "250" but got code "552", with message "552 5.2.2 Mailbox full".',
        ];
        Mail::shouldReceive('html')->twice()->andReturnUsing(function () use (&$messages) {
            throw new \RuntimeException(array_shift($messages));
        });
        $this->assertSame(2, app(EmailingService::class)->runBatch());
        $gone = DB::table('mailing_contacts')->where('email', 'gone@example.test')->first();
        $full = DB::table('mailing_contacts')->where('email', 'full@example.test')->first();
        $this->assertNotNull($gone->bounced_at);
        $this->assertSame('hard', $gone->bounce_type);
        $this->assertNull($full->bounced_at);
        $this->assertSame(1, $full->soft_bounces);
        $this->assertDatabaseHas('mailing_deliveries', ['contact_id' => $gone->id, 'status' => 'bounced']);
        $this->assertDatabaseHas('mailing_deliveries', ['contact_id' => $full->id, 'status' => 'failed']);

        $service = app(EmailingService::class);
        $this->assertFalse($service->recordBounce('full@example.test', 'soft'));
        $this->assertTrue($service->recordBounce('full@example.test', 'soft'));
        $this->assertSame('soft_limit', DB::table('mailing_contacts')->where('id', $full->id)->value('bounce_type'));
        // Opätovné pridanie adresy ju neoživí; až ručná reaktivácia.
        $this->contact('gone@example.test');
        $this->assertNotNull(DB::table('mailing_contacts')->where('id', $gone->id)->value('bounced_at'));
        $this->postJson("/api/emailing/contacts/{$gone->id}/reactivate")->assertNoContent();
        $this->assertNull(DB::table('mailing_contacts')->where('id', $gone->id)->value('bounced_at'));
    }

    public function test_messages_have_individual_recipient_and_unsubscribe_headers(): void
    {
        $this->admin();
        config(['mail.mailers.smtp' => ['transport' => 'array']]);
        $this->contact('a@example.test b@example.test');
        $id = $this->campaign();
        $this->postJson("/api/emailing/campaigns/$id/queue")->assertOk();
        app(EmailingService::class)->runBatch();
        $messages = Mail::mailer()->getSymfonyTransport()->messages();
        $this->assertCount(2, $messages);
        $first = $messages[0]->getOriginalMessage();
        $second = $messages[1]->getOriginalMessage();
        $this->assertCount(1, $first->getTo());
        $this->assertSame('a@example.test', $first->getTo()[0]->getAddress());
        $this->assertSame([], $first->getCc());
        $this->assertSame([], $first->getBcc());
        $this->assertSame('List-Unsubscribe=One-Click', $first->getHeaders()->get('List-Unsubscribe-Post')->getBodyAsString());
        $contact = DB::table('mailing_contacts')->where('email', 'a@example.test')->first();
        $url = \Illuminate\Support\Facades\URL::secure('/api/emailing/unsubscribe/'.$contact->token);
        $this->assertSame('<'.$url.'>', $first->getHeaders()->get('List-Unsubscribe')->getBodyAsString());
        $this->assertStringStartsWith('https://', $url);
        $this->assertStringContainsString('href="'.$url.'"', $first->getHtmlBody());
        $this->assertLessThan(strpos($first->getHtmlBody(), 'ZÁSTAVY'), strpos($first->getHtmlBody(), 'Zrušiť odber'));
        $this->assertNotSame($first->getHeaders()->get('List-Unsubscribe')->getBodyAsString(), $second->getHeaders()->get('List-Unsubscribe')->getBodyAsString());
        $this->assertStringContainsString('Odhlásiť sa', $first->getHtmlBody());
        $this->postJson('/api/emailing/test', $this->content())->assertOk();
        $test = $messages->last()->getOriginalMessage();
        $this->assertSame(auth()->user()->email, $test->getTo()[0]->getAddress());
        $this->assertSame('[TEST] Ponuka', $test->getSubject());
        $this->assertNull($test->getHeaders()->get('List-Unsubscribe'));
        $this->assertNull($test->getHeaders()->get('List-Unsubscribe-Post'));
    }

    public function test_disabled_transport_never_sends_and_templates_are_reusable(): void
    {
        $this->admin();
        $this->contact();
        $id = $this->campaign();
        config(['mail.default' => 'log']);
        $this->postJson("/api/emailing/campaigns/$id/queue")->assertUnprocessable();
        Mail::shouldReceive('html')->never();
        $this->assertSame(0, app(EmailingService::class)->runBatch());
        $this->postJson('/api/emailing/templates', $this->content())->assertOk();
        $this->getJson('/api/emailing')->assertOk()->assertJsonCount(1, 'templates');
    }

    public function test_spisor_layout_survives_save_and_uses_personal_unsubscribe(): void
    {
        $this->admin();
        $data = $this->content() + ['layout' => 'spisor'];
        $this->postJson('/api/emailing/templates', $data)->assertOk()->assertJsonPath('layout', 'spisor');
        $id = $this->postJson('/api/emailing/campaigns', $data)->assertOk()->assertJsonPath('layout', 'spisor')->json('id');
        $campaign = DB::table('mailing_campaigns')->find($id);
        $this->contact();
        $contact = DB::table('mailing_contacts')->first();
        $html = app(EmailingService::class)->html($campaign, $contact);
        $this->assertStringContainsString('Spisor.eu', $html);
        $this->assertLessThan(strpos($html, 'Spisor.eu'), strpos($html, 'Zrušiť odber'));
        $this->assertStringContainsString('Nezvratná redakcia', $html);
        $this->assertStringContainsString($contact->email, $html);
        $this->assertStringContainsString('/emailing/unsubscribe/'.$contact->token, $html);
        $this->assertStringContainsString('&lt;script&gt;', $html);
        $this->assertStringNotContainsString('<script>', $html);
        $this->assertStringNotContainsString('{unsubscribe}', $html);
        $this->assertStringNotContainsString('{subtag:', $html);
        $this->assertStringNotContainsString('poweredby_black.png', $html);
        $preview = $this->postJson('/api/emailing/preview', $data)->assertOk()->json('html');
        $this->assertStringContainsString('Náhľad / test', $preview);
        $this->assertStringNotContainsString($contact->token, $preview);
        $this->postJson('/api/emailing/campaigns', array_replace($data, ['layout' => '../welcome']))->assertUnprocessable();
    }

    public function test_flags_catalog_is_reusable_and_renders_products_and_safe_content(): void
    {
        $this->admin();
        foreach ([1, 2, 5, 6] as $productId) {
            $product = \App\Models\Product::create(['id' => $productId, 'name' => 'Product '.$productId, 'code' => 'TEST-'.$productId, 'vat' => 23, 'published' => true]);
            $product->images()->create(['name' => 'Catalog image', 'path' => 'public/catalog-'.$productId.'.jpg', 'disk' => 'public', 'sort_order' => 0]);
        }
        $data = array_replace($this->content(), ['layout' => 'flags-catalog', 'heading' => 'Nová vlajka. Dôstojný prvý dojem.']);
        $this->postJson('/api/emailing/templates', $data)->assertOk()->assertJsonPath('layout', 'flags-catalog');
        $id = $this->postJson('/api/emailing/campaigns', $data)->assertOk()->assertJsonPath('layout', 'flags-catalog')->json('id');
        $this->putJson('/api/emailing/campaigns/'.$id, $data)->assertOk()->assertJsonPath('layout', 'flags-catalog');
        $this->contact();
        $contact = DB::table('mailing_contacts')->first();
        $campaign = DB::table('mailing_campaigns')->find($id);
        $campaign->coupon_code = 'TEST-COUPON';
        $html = app(EmailingService::class)->html($campaign, $contact);
        foreach (['Vlajka Slovenskej republiky', 'Vlajka Európskej únie', 'Štátne symboly do tried', 'Štátny znak SR na stenu', '23,00 €', '29,00 €', '14,68 €', 'TEST-COUPON', 'Nová vlajka.<br>Dôstojný prvý dojem.', route('images.show', ['image' => \App\Models\Product::find(1)->images()->first()->id]), 'https://zastavy-vlajky.sk/product/7/show/obecne-zastavy', $contact->email, $contact->token, '&lt;script&gt;', 'Zrušiť odber'] as $expected) {
            $this->assertStringContainsString($expected, $html);
        }
        foreach (['<script>', '{subtag:', '{unsubscribe}', 'poweredby_black.png', 'https://api.zastavy-vlajky.sk/storage/'] as $unwanted) {
            $this->assertStringNotContainsString($unwanted, $html);
        }
        $preview = $this->postJson('/api/emailing/preview', array_replace($data, ['heading' => '<b>Vlastný nadpis</b>', 'button_url' => null]))->assertOk()->json('html');
        $this->assertStringContainsString('&lt;b&gt;Vlastný nadpis&lt;/b&gt;', $preview);
        $this->assertStringContainsString('Náhľad / test', $preview);
        $this->assertStringNotContainsString($contact->token, $preview);
        $this->assertStringNotContainsString('Prezrieť celú ponuku →', $preview);
    }
}
