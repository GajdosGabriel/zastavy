<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\EmailOrderDraft;
use App\Models\Order;
use App\Models\OrderMessage;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\SalesQuote;
use App\Models\ShippingMethod;
use App\Models\User;
use App\Services\Companies\CompanyRegistry;
use App\Services\OpenAI\EmailOrderAI;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Objednávky z e-mailu: Account pošle nový mail mimo vlákna objednávky
 * (message.unassigned), AI pripraví návrh, úplný vznikne sám.
 */
class EmailOrderInboxTest extends TestCase
{
    use RefreshDatabase;

    private ProductVariant $variant;

    private ShippingMethod $shipping;

    private Customer $customer;

    protected function setUp(): void
    {
        parent::setUp();

        Mail::fake();
        Storage::fake('local');

        config()->set('account.url', 'http://account.test');
        config()->set('account.token', 'acc_test');
        config()->set('account.webhook_secret', 'whsec_test');
        config()->set('account.messaging', true);
        config()->set('account.email_orders.enabled', true);
        config()->set('services.openai.key', 'test-only');

        Http::fake([
            'account.test/api/v1/messages' => Http::response(['data' => ['conversation' => ['id' => 'conv-1'], 'message' => ['id' => 'm-1']]], 202),
            'account.test/messaging/attachments/*' => Http::response('SKEN'),
        ]);

        $product = Product::create(['name' => 'Zástava SR', 'code' => 'SR', 'published' => true, 'vat' => 23, 'unit_value' => 'ks']);
        $this->variant = $product->variants()->create(['name' => '100 × 150 cm', 'code' => 'SR-100', 'published' => true, 'price' => 25, 'is_default' => true, 'min_order' => 1]);
        $this->shipping = ShippingMethod::create(['name' => 'Pošta', 'price' => 5, 'active' => true]);
        $this->customer = Customer::create(['company' => 'Obec Lehota', 'email' => 'obec@lehota.test', 'ico' => '00123456', 'dic' => '2020123456',
            'street' => 'Hlavná 1', 'city' => 'Lehota', 'postcode' => '94901', 'phone' => '0900123456']);

        $this->mock(CompanyRegistry::class)->shouldReceive('find')->andReturn(null);
    }

    public function test_mail_ktory_nie_je_objednavka_sa_len_oznaci(): void
    {
        $this->ai(['kind' => 'other']);

        $this->webhook()->assertOk();

        $draft = EmailOrderDraft::sole();
        $this->assertSame(EmailOrderDraft::NOT_ORDER, $draft->status);
        $this->assertDatabaseCount('orders', 0);
    }

    public function test_opakovany_webhook_nevytvori_druhy_navrh(): void
    {
        $this->ai(['kind' => 'other']);

        $this->webhook()->assertOk();
        $this->webhook()->assertOk();

        $this->assertDatabaseCount('email_order_drafts', 1);
    }

    public function test_bez_zapnutia_sa_mail_ignoruje(): void
    {
        config()->set('account.email_orders.enabled', false);

        $this->webhook()->assertOk();

        $this->assertDatabaseCount('email_order_drafts', 0);
    }

    public function test_uplna_objednavka_bez_auto_create_ostane_navrhom(): void
    {
        $this->ai();

        $this->webhook()->assertOk();

        $draft = EmailOrderDraft::sole();
        $this->assertSame(EmailOrderDraft::DRAFT, $draft->status);
        $this->assertContains('Automatické vytváranie objednávok je vypnuté.', $draft->reasons);
        $this->assertDatabaseCount('orders', 0);
        $this->assertSame(1, $draft->attachments()->count());
    }

    public function test_neuplna_objednavka_ostane_navrhom_s_dovodmi(): void
    {
        $this->autoCreate();
        $this->ai(['items' => [['description' => 'vlajky obce', 'variant_id' => null, 'quantity' => 3]], 'warnings' => ['Chýba rozmer.']]);

        $this->webhook()->assertOk();

        $draft = EmailOrderDraft::sole();
        $this->assertSame(EmailOrderDraft::DRAFT, $draft->status);
        $this->assertContains('Nespárovaná položka: vlajky obce', $draft->reasons);
        $this->assertContains('AI: Chýba rozmer.', $draft->reasons);
        $this->assertDatabaseCount('orders', 0);
    }

    public function test_cudzi_odosielatel_neobjedna_na_existujucu_firmu(): void
    {
        $this->autoCreate();
        $this->ai();

        $this->webhook(from: 'niekto@inde.test')->assertOk();

        $this->assertContains('Odosielateľ nie je kontaktom nájdeného zákazníka.', EmailOrderDraft::sole()->reasons);
        $this->assertDatabaseCount('orders', 0);
    }

    public function test_uplna_objednavka_vznikne_automaticky_a_odpoved_ide_do_vlakna_mailu(): void
    {
        $this->autoCreate();
        $this->ai();

        $this->webhook()->assertOk();

        $order = Order::sole();
        $draft = EmailOrderDraft::sole();
        $this->assertSame(EmailOrderDraft::CREATED, $draft->status);
        $this->assertTrue($draft->auto_created);
        $this->assertSame($order->id, $draft->order_id);
        $this->assertSame($this->customer->id, $order->customer_id);
        $this->assertSame(2, (int) $order->orderProducts()->sole()->quantity);
        $this->assertSame($this->shipping->id, (int) $order->shipping_method_id);
        $this->assertStringContainsString('Objednávka z e-mailu od Starosta <obec@lehota.test>', $order->note);
        $this->assertStringContainsString('(vytvorená automaticky)', $order->note);

        // Pôvodný mail je prvá správa zákazníka aj so skenom
        $message = OrderMessage::where('order_id', $order->id)->sole();
        $this->assertSame('customer', $message->direction);
        $this->assertStringContainsString('2 ks zástavy SR', $message->body);
        $this->assertNull($message->read_at);
        $this->assertSame('sken.jpg', $message->attachments()->sole()->name);

        // Potvrdenie odišlo do konverzácie zákazníkovho mailu
        Http::assertSent(fn (Request $request) => $request->url() === 'http://account.test/api/v1/messages'
            && $request['conversation'] === 'b2c1d1f0-0000-4000-8000-000000000001'
            && $request['ref']['id'] === $order->uuid
            && $request['kind'] === 'order.OrderCreated');
    }

    public function test_obsluha_vytvori_objednavku_z_navrhu_v_kosiku(): void
    {
        $this->ai(['items' => [['description' => 'vlajky obce', 'variant_id' => null, 'quantity' => 3]]]);
        $this->webhook()->assertOk();
        $draft = EmailOrderDraft::sole();

        $staff = User::factory()->create();
        $staff->assignRole('super-admin');
        Sanctum::actingAs($staff);

        $this->getJson('/api/email-order-drafts')->assertOk()->assertJsonPath('counts.open', 1)->assertJsonPath('data.0.id', $draft->id);
        $this->getJson('/api/email-order-drafts/'.$draft->id)->assertOk()->assertJsonPath('data.body', "Dobrý deň,\nobjednávame 2 ks zástavy SR 100x150.\nStarosta");

        $payload = [
            'customer' => ['id' => $this->customer->id, 'company' => 'Obec Lehota', 'name' => 'Starosta', 'email' => 'obec@lehota.test',
                'phone' => '0900123456', 'street' => 'Hlavná 1', 'postcode' => '94901', 'city' => 'Lehota', 'ico' => '00123456'],
            'orderProducts' => [['id' => $this->variant->product_id, 'variant_id' => $this->variant->id, 'input_order' => 3]],
            'email_order_draft_id' => $draft->id,
        ];
        $this->postCheckout($payload)->assertOk();

        $order = Order::sole();
        $this->assertSame($order->id, $draft->fresh()->order_id);
        $this->assertFalse($draft->fresh()->auto_created);
        $this->assertNotNull(OrderMessage::where('order_id', $order->id)->sole()->read_at);

        // Z toho istého mailu druhá objednávka nevznikne
        $this->postCheckout($payload)->assertUnprocessable()->assertJsonValidationErrors('email_order_draft_id');
    }

    public function test_dopyt_na_cenovu_ponuku_zalozi_ponuku_s_povodnym_mailom(): void
    {
        $this->autoCreate();
        $this->ai(['kind' => 'quote', 'items' => [
            ['description' => 'zástava SR 100x150', 'variant_id' => $this->variant->id, 'quantity' => 2],
            ['description' => 'vlajka obce s erbom', 'variant_id' => null, 'quantity' => 5],
        ], 'note' => 'Termín do konca mesiaca.']);

        $this->webhook()->assertOk();

        $draft = EmailOrderDraft::sole();
        $quote = SalesQuote::sole();
        $this->assertSame(EmailOrderDraft::CREATED, $draft->status);
        $this->assertSame($quote->id, $draft->sales_quote_id);
        $this->assertDatabaseCount('orders', 0);

        $this->assertSame('requested', $quote->fresh()->status);
        $this->assertSame('email', $quote->source);
        $this->assertSame($this->customer->id, $quote->customer_id);
        $this->assertSame('obec@lehota.test', $quote->customer['email']);
        // assertEquals: MySQL JSON stĺpec mení poradie kľúčov
        $this->assertEquals([['variant_id' => $this->variant->id, 'name' => 'Zástava SR / 100 × 150 cm', 'quantity' => 2]], $quote->requested_items);
        $this->assertStringContainsString('Termín do konca mesiaca.', $quote->brief);
        $this->assertStringContainsString('- vlajka obce s erbom (5 ks)', $quote->brief);
        $this->assertSame('sken.jpg', $quote->attachments()->sole()->name);

        // Pôvodný mail je neprečítaná správa zákazníka v konverzácii ponuky
        $message = $quote->messages()->sole();
        $this->assertSame('customer', $message->direction);
        $this->assertNull($message->read_at);
        $this->assertStringContainsString('2 ks zástavy SR', $message->body);

        $staff = User::factory()->create();
        $staff->assignRole('super-admin');
        Sanctum::actingAs($staff);
        $this->getJson('/api/email-order-drafts?tab=created')->assertOk()->assertJsonPath('data.0.quote.number', $quote->number);
        $this->patchJson('/api/email-order-drafts/'.$draft->id, ['status' => 'not_order'])->assertUnprocessable();
    }

    public function test_navrh_z_emailu_nesmie_poslat_zakaznik(): void
    {
        $this->ai(['kind' => 'other']);
        $this->webhook()->assertOk();

        $this->postCheckout([
            'customer' => ['company' => 'X', 'name' => 'X', 'email' => 'x@x.test', 'phone' => '1', 'street' => 'S', 'postcode' => '1', 'city' => 'C'],
            'orderProducts' => [['id' => $this->variant->product_id, 'variant_id' => $this->variant->id, 'input_order' => 1]],
            'email_order_draft_id' => EmailOrderDraft::sole()->id,
        ])->assertUnprocessable()->assertJsonValidationErrors('email_order_draft_id');
    }

    /* ------------------------------------------------------------- */

    private function autoCreate(): void
    {
        config()->set('account.email_orders.auto_create', true);
        config()->set('account.email_orders.shipping_method_id', $this->shipping->id);
    }

    /** @param array<string, mixed> $override */
    private function ai(array $override = []): void
    {
        $draft = array_replace([
            'kind' => 'order',
            'customer' => array_replace(array_fill_keys(EmailOrderAI::FIELDS, ''), [
                'company' => 'Obec Lehota', 'name' => 'Starosta', 'email' => 'obec@lehota.test', 'ico' => '00123456',
            ]),
            'items' => [['description' => 'zástava SR 100x150', 'variant_id' => $this->variant->id, 'quantity' => 2]],
            'note' => '',
            'warnings' => [],
        ], $override);

        $this->mock(EmailOrderAI::class, function ($mock) use ($draft) {
            $mock->shouldReceive('extract')->andReturn($draft);
            $mock->shouldNotReceive('research');
        });
    }

    private function webhook(string $from = 'obec@lehota.test'): TestResponse
    {
        $body = json_encode([
            'id' => 'evt-'.uniqid(),
            'event' => 'message.unassigned',
            'data' => [
                'conversation' => ['id' => 'b2c1d1f0-0000-4000-8000-000000000001', 'ref' => ['type' => null, 'id' => null], 'contact_email' => $from],
                'message' => [
                    'id' => 'a1b2c3d4-0000-4000-8000-000000000001',
                    'direction' => 'in',
                    'from' => $from,
                    'from_name' => 'Starosta',
                    'subject' => 'Objednávka vlajok',
                    'text' => "Dobrý deň,\nobjednávame 2 ks zástavy SR 100x150.\nStarosta",
                    'attachments' => [['filename' => 'sken.jpg', 'mime' => 'image/jpeg', 'url' => 'http://account.test/messaging/attachments/1']],
                    'created_at' => '2026-10-21T08:30:00+02:00',
                ],
            ],
        ]);
        $timestamp = time();

        return $this->call('POST', '/api/webhooks/account', server: [
            'HTTP_X_ACCOUNTS_TIMESTAMP' => $timestamp,
            'HTTP_X_ACCOUNTS_SIGNATURE' => hash_hmac('sha256', $timestamp.'.'.$body, 'whsec_test'),
            'CONTENT_TYPE' => 'application/json',
            'HTTP_ACCEPT' => 'application/json',
        ], content: $body);
    }
}
