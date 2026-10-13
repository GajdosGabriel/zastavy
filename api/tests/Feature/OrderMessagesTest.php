<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\OrderMessage;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use App\Notifications\OrderMessageReceived;
use App\Notifications\OrderMessageSent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class OrderMessagesTest extends TestCase
{
    use RefreshDatabase;

    private ProductVariant $variant;

    private array $customer = ['company' => 'Test buyer', 'name' => 'Buyer', 'email' => 'buyer@example.test', 'phone' => '0900123456', 'street' => 'Street 1', 'city' => 'Nitra', 'postcode' => '94901'];

    protected function setUp(): void
    {
        parent::setUp();
        Notification::fake();
        Storage::fake('local');
        $p = Product::create(['name' => 'Flag', 'code' => 'FLAG', 'published' => true, 'vat' => 23, 'unit_value' => 'ks']);
        $this->variant = $p->variants()->create(['name' => 'Large', 'code' => 'FLAG-L', 'published' => true, 'price' => 12, 'is_default' => true, 'min_order' => 1]);
    }

    private function staff(): User
    {
        $u = User::factory()->create();
        $u->assignRole('super-admin');
        Sanctum::actingAs($u);

        return $u;
    }

    private function order(): Order
    {
        $this->postCheckout(['customer' => $this->customer, 'orderProducts' => [['id' => $this->variant->product_id, 'variant_id' => $this->variant->id, 'input_order' => 2]]])->assertOk();

        return Order::latest('id')->firstOrFail();
    }

    /** Token z odkazu, ktorý by zákazník dostal v e-maile. */
    private function token(string $html): string
    {
        $this->assertSame(1, preg_match('~/objednavka/[0-9a-f-]{36}/spravy#token=([A-Za-z0-9]{64})~', $html, $m), 'E-mail neobsahuje odkaz na správy.');

        return $m[1];
    }

    public function test_staff_message_is_emailed_with_a_stable_reply_link(): void
    {
        $o = $this->order();
        $this->staff();
        $this->postJson('/api/orders/'.$o->id.'/messages', ['body' => "Dobrý deň,\nposielame <b>otázku</b>."])->assertOk()->assertJsonPath('messages.0.direction', 'staff')->assertJsonPath('messages.0.kind', 'message');
        Notification::assertSentTo($o, OrderMessageSent::class);
        $first = OrderMessage::firstOrFail();
        $html = (string) (new OrderMessageSent($first))->toMail($o)->render();
        $this->assertStringContainsString('&lt;b&gt;otázku&lt;/b&gt;', $html);
        $token = $this->token($html);
        $this->getJson('/api/public-artworks/'.$o->uuid, ['X-Sales-Token' => $token])->assertOk()->assertJsonCount(1, 'messages')->assertJsonMissingPath('messages.0.user_id');

        $this->postJson('/api/orders/'.$o->id.'/messages', ['body' => 'Druhá správa'])->assertOk();
        $this->assertSame($token, $this->token((string) (new OrderMessageSent(OrderMessage::latest('id')->first()))->toMail($o)->render()));
        // Objednávka iba so správami do plánu výroby nepatrí.
        $this->getJson('/api/production')->assertOk()->assertJsonCount(0, 'data');
    }

    public function test_customer_can_start_the_conversation_from_the_confirmation_email(): void
    {
        $o = $this->order();
        $token = $this->token(view('emails.orderConfirmation', ['order' => $o])->render());
        $url = '/api/public-artworks/'.$o->uuid.'/messages';
        $this->postJson($url, ['name' => 'Buyer', 'body' => 'Chcem ešte skoby na pravej strane.'])->assertNotFound();
        $this->post($url, ['name' => 'Buyer', 'body' => 'Chcem ešte skoby na pravej strane.', 'files' => [UploadedFile::fake()->create('nacrt.pdf', 2, 'application/pdf')]], ['X-Sales-Token' => $token, 'Accept' => 'application/json'])
            ->assertOk()->assertJsonPath('messages.0.direction', 'customer')->assertJsonPath('messages.0.attachments.0.name', 'nacrt.pdf');
        $admin = $this->staff();
        $message = OrderMessage::firstOrFail();
        $this->assertStringContainsString('skoby', (string) (new OrderMessageReceived($message))->toMail($admin)->render());

        $this->getJson('/api/dashboard/notifications')->assertOk()->assertJsonPath('data.messages_unread', 1);
        $this->getJson('/api/order-messages/unread')->assertOk()->assertJsonPath('0.order_id', $o->id)->assertJsonPath('0.unread', 1);
        $this->getJson('/api/orders/'.$o->id)->assertOk()->assertJsonPath('unread_messages', 1);
        $this->getJson('/api/orders/'.$o->id.'/production')->assertOk()->assertJsonCount(1, 'messages');
        $this->getJson('/api/dashboard/notifications')->assertOk()->assertJsonPath('data.messages_unread', 0);
        $this->getJson('/api/order-messages/unread')->assertOk()->assertJsonCount(0);
    }

    public function test_staff_are_notified_about_customer_messages_and_decisions(): void
    {
        $o = $this->order();
        $admin = $this->staff();
        $link = $this->postJson('/api/orders/'.$o->id.'/artworks', ['file' => UploadedFile::fake()->create('design.pdf', 2, 'application/pdf')])->assertOk()->json();
        $this->postJson('/api/orders/'.$o->id.'/messages', ['body' => 'Návrh', 'artwork_version' => 2])->assertConflict();
        $this->postJson('/api/orders/'.$o->id.'/messages', ['body' => 'Posielame návrh na schválenie.', 'artwork_version' => 1])->assertOk()->assertJsonPath('messages.0.kind', 'artwork_sent')->assertJsonPath('messages.0.version', 1);
        $this->assertStringContainsString('verzia 1', (string) (new OrderMessageSent(OrderMessage::firstOrFail()))->toMail($o)->render());

        $headers = ['X-Sales-Token' => $link['token']];
        $this->postJson('/api/public-artworks/'.$o->uuid.'/messages', ['name' => 'Buyer', 'body' => 'Kedy to bude?'], $headers)->assertOk();
        $this->postJson('/api/public-artworks/'.$o->uuid.'/decision', ['action' => 'comment', 'version' => 1, 'name' => 'Buyer', 'comment' => 'Upraviť farbu'], $headers)->assertOk()->assertJsonPath('messages.2.kind', 'changes_requested')->assertJsonPath('messages.2.body', 'Upraviť farbu');
        $this->postJson('/api/public-artworks/'.$o->uuid.'/decision', ['action' => 'approve', 'version' => 1, 'name' => 'Buyer', 'confirm' => true], $headers)->assertOk()->assertJsonPath('messages.3.kind', 'approved');
        // Opakované schválenie je idempotentné a novú udalosť nevytvorí.
        $this->postJson('/api/public-artworks/'.$o->uuid.'/decision', ['action' => 'approve', 'version' => 1, 'name' => 'Buyer', 'confirm' => true], $headers)->assertOk()->assertJsonCount(4, 'messages');
        Notification::assertSentToTimes($admin, OrderMessageReceived::class, 3);
        $this->assertStringContainsString('schválil', (string) (new OrderMessageReceived(OrderMessage::latest('id')->first()))->toMail($admin)->render());
    }

    public function test_message_files_are_private_and_scoped_to_the_order(): void
    {
        $o = $this->order();
        $other = $this->order();
        $this->staff();
        $this->post('/api/orders/'.$o->id.'/messages', ['body' => 'Príloha', 'files' => [UploadedFile::fake()->create('nahlad.pdf', 2, 'application/pdf')]], ['Accept' => 'application/json'])->assertOk();
        $this->postJson('/api/orders/'.$other->id.'/messages', ['body' => 'Iná objednávka'])->assertOk();
        $message = OrderMessage::where('order_id', $o->id)->firstOrFail();
        $file = $message->attachments()->firstOrFail();
        Storage::disk('local')->assertExists($file->path);
        $token = $this->token((string) (new OrderMessageSent($message))->toMail($o)->render());
        $foreign = $this->token((string) (new OrderMessageSent(OrderMessage::where('order_id', $other->id)->firstOrFail()))->toMail($other)->render());
        $url = '/api/public-artworks/'.$o->uuid.'/messages/'.$message->id.'/files/'.$file->id;
        $this->getJson($url)->assertOk();
        $this->getJson($url, ['X-Sales-Token' => $token])->assertOk();
        $this->getJson($url, ['X-Sales-Token' => $foreign])->assertNotFound();
        $this->getJson('/api/public-artworks/'.$other->uuid.'/messages/'.$message->id.'/files/'.$file->id, ['X-Sales-Token' => $foreign])->assertNotFound();

        Sanctum::actingAs(User::factory()->create());
        $this->getJson($url)->assertForbidden();
        $this->postJson('/api/orders/'.$o->id.'/messages', ['body' => 'Cudzí'])->assertForbidden();
        $this->getJson('/api/order-messages/unread')->assertForbidden();
    }
}
