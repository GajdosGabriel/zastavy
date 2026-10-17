<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\OrderEmail;
use App\Models\OrderMessage;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use App\Support\OrderThread;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * E-maily zákazníkom cez Account (ACCOUNT_MESSAGING) a odpovede zákazníkov
 * späť do konverzácie objednávky.
 */
class AccountMessagingTest extends TestCase
{
    use RefreshDatabase;

    private ProductVariant $variant;

    private array $customer = ['company' => 'Test buyer', 'name' => 'Buyer', 'email' => 'buyer@example.test', 'phone' => '0900123456', 'street' => 'Street 1', 'city' => 'Nitra', 'postcode' => '94901'];

    protected function setUp(): void
    {
        parent::setUp();

        Mail::fake();
        Storage::fake('local');

        config()->set('account.url', 'http://account.test');
        config()->set('account.token', 'acc_test');
        config()->set('account.webhook_secret', 'whsec_test');
        config()->set('account.messaging', true);

        Http::fake([
            'account.test/api/v1/messages' => Http::response(['data' => ['conversation' => ['id' => 'c-1'], 'message' => ['id' => 'm-1']]], 202),
            'account.test/messaging/attachments/*' => Http::response('PNGDATA'),
        ]);

        $p = Product::create(['name' => 'Flag', 'code' => 'FLAG', 'published' => true, 'vat' => 23, 'unit_value' => 'ks']);
        $this->variant = $p->variants()->create(['name' => 'Large', 'code' => 'FLAG-L', 'published' => true, 'price' => 12, 'is_default' => true, 'min_order' => 1]);
    }

    public function test_order_mails_go_through_account_with_order_ref_and_thread(): void
    {
        $order = $this->order();

        $staff = User::factory()->create();
        $staff->assignRole('super-admin');
        Sanctum::actingAs($staff);

        $this->postJson('/api/orders/'.$order->id.'/messages', ['body' => 'Posielame návrh.'])->assertOk();

        Http::assertSent(fn (Request $request) => $request->url() === 'http://account.test/api/v1/messages'
            && $request['to'] === 'buyer@example.test'
            && $request['ref']['type'] === 'order'
            && $request['ref']['id'] === $order->uuid
            && str_contains($request['ref']['label'], 'Test buyer')
            && $request['thread_id'] === OrderThread::id($order)
            && $request['kind'] === 'order.OrderMessageSent');

        // Potvrdenie aj správa sú v histórii e-mailov objednávky
        $this->assertSame(['OrderCreated', 'OrderMessageSent'], OrderEmail::where('order_id', $order->id)->orderBy('id')->pluck('type')->all());
        $this->assertSame('sent', OrderEmail::where('type', 'OrderMessageSent')->value('status'));
        Mail::assertNothingOutgoing();
    }

    public function test_customer_reply_becomes_order_message_with_attachment(): void
    {
        $order = $this->order();

        $this->webhook('message.received', $order, [
            'id' => '11111111-1111-1111-1111-111111111111',
            'from' => 'buyer@example.test',
            'from_name' => 'Ján Buyer',
            'reply_text' => 'Grafiku schvaľujem.',
            'text' => "Grafiku schvaľujem.\n\n> pôvodná správa",
            'attachments' => [['filename' => 'logo.png', 'mime' => 'image/png', 'url' => 'http://account.test/messaging/attachments/abc?signature=x']],
        ])->assertOk();

        $message = OrderMessage::where('order_id', $order->id)->where('direction', 'customer')->sole();
        $this->assertSame('Grafiku schvaľujem.', $message->body);
        $this->assertSame('Ján Buyer', $message->author);
        $this->assertNull($message->read_at);

        $file = $message->attachments()->sole();
        $this->assertSame('logo.png', $file->name);
        $this->assertSame('PNGDATA', Storage::disk('local')->get($file->path));
    }

    public function test_repeated_webhook_does_not_duplicate_message(): void
    {
        $order = $this->order();
        $payload = ['id' => '22222222-2222-2222-2222-222222222222', 'from' => 'buyer@example.test', 'reply_text' => 'Ok'];

        $this->webhook('message.received', $order, $payload)->assertOk();
        $this->webhook('message.received', $order, $payload)->assertOk();

        $this->assertSame(1, OrderMessage::where('order_id', $order->id)->where('direction', 'customer')->count());
    }

    public function test_inbox_reply_is_mirrored_as_staff_message(): void
    {
        $order = $this->order();

        $this->webhook('message.sent', $order, [
            'id' => '33333333-3333-3333-3333-333333333333', 'source' => 'project', 'text' => 'z projektu',
        ])->assertOk();
        $this->webhook('message.sent', $order, [
            'id' => '44444444-4444-4444-4444-444444444444', 'source' => 'inbox', 'sent_by' => 'Gabo',
            'text' => 'Zajtra to bude.', 'subject' => 'Re: Objednávka', 'to' => 'buyer@example.test',
        ])->assertOk();

        $message = OrderMessage::where('order_id', $order->id)->where('direction', 'staff')->sole();
        $this->assertSame('Zajtra to bude.', $message->body);
        $this->assertSame('Gabo', $message->author);
        $this->assertTrue(OrderEmail::where('order_id', $order->id)->where('type', 'InboxReply')->exists());
    }

    public function test_bounce_is_recorded_in_email_history(): void
    {
        $order = $this->order();

        $this->webhook('message.bounced', $order, [
            'id' => '55555555-5555-5555-5555-555555555555', 'to' => 'buyer@example.test',
            'error' => '5.1.1 user unknown', 'meta' => ['notification' => 'OrderCreated'],
        ])->assertOk();

        $email = OrderEmail::where('order_id', $order->id)->where('status', 'failed')->sole();
        $this->assertStringContainsString('5.1.1 user unknown', $email->error);
    }

    public function test_bad_signature_is_rejected(): void
    {
        $this->call('POST', '/api/webhooks/account', server: [
            'HTTP_X_ACCOUNTS_TIMESTAMP' => time(),
            'HTTP_X_ACCOUNTS_SIGNATURE' => 'nezmysel',
            'CONTENT_TYPE' => 'application/json',
        ], content: '{"event":"message.received","data":{}}')->assertStatus(400);
    }

    public function test_without_messaging_flag_mail_goes_locally(): void
    {
        config()->set('account.messaging', false);

        $this->order();

        Http::assertNothingSent();
    }

    /* ------------------------------------------------------------- */

    private function order(): Order
    {
        $this->postCheckout(['customer' => $this->customer, 'orderProducts' => [['id' => $this->variant->product_id, 'variant_id' => $this->variant->id, 'input_order' => 2]]])->assertOk();

        return Order::latest('id')->firstOrFail();
    }

    /** @param array<string, mixed> $message */
    private function webhook(string $event, Order $order, array $message): \Illuminate\Testing\TestResponse
    {
        $body = json_encode([
            'id' => 'evt-'.uniqid(),
            'event' => $event,
            'data' => [
                'conversation' => [
                    'id' => 'conv-1',
                    'ref' => ['type' => 'order', 'id' => $order->uuid, 'label' => 'Objednávka'],
                    'contact_email' => 'buyer@example.test',
                ],
                'message' => $message,
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
