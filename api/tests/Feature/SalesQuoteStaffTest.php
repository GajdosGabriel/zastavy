<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\SalesQuote;
use App\Models\ShippingMethod;
use App\Models\User;
use App\Notifications\QuoteMessageReceived;
use App\Notifications\QuoteMessageSent;
use App\Notifications\QuoteOffered;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Notification;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SalesQuoteStaffTest extends TestCase
{
    use RefreshDatabase;

    private ProductVariant $variant;

    private ShippingMethod $shipping;

    private array $customer = ['company' => 'Obec Lužianky', 'name' => 'Jana Nová', 'email' => 'obec@example.test', 'phone' => '0900123456', 'street' => 'Hlavná 1', 'city' => 'Lužianky', 'postcode' => '95141', 'ico' => '00308072'];

    protected function setUp(): void
    {
        parent::setUp();
        Notification::fake();
        $p = Product::create(['name' => 'Vlajka SR', 'code' => 'SR', 'published' => true, 'vat' => 23, 'unit_value' => 'ks']);
        $this->variant = $p->variants()->create(['name' => '100 × 150', 'code' => 'SR-L', 'published' => true, 'price' => 20, 'is_default' => true, 'min_order' => 5]);
        $this->shipping = ShippingMethod::create(['name' => 'Kuriér', 'price' => 5, 'active' => true]);
        $u = User::factory()->create();
        $u->assignRole('super-admin');
        Sanctum::actingAs($u);
    }

    private function create(): array
    {
        return $this->postJson('/api/sales-quotes', ['customer' => $this->customer, 'title' => 'Zástavy pre obec', 'internal_note' => 'Volal starosta'])->assertCreated()->json();
    }

    private function payload(array $extra = []): array
    {
        return array_merge(['version' => 0, 'customer' => $this->customer, 'items' => [
            ['variant_id' => $this->variant->id, 'quantity' => 10, 'price_net' => 10, 'discount_percent' => 10],
            ['is_custom' => true, 'name' => 'Zástava s erbom obce', 'description' => '150 × 300 cm, obšitie', 'unit' => 'ks', 'vat' => 23, 'quantity' => 2, 'price_net' => 99.99],
        ], 'discount_percent' => 5, 'shipping_method_id' => $this->shipping->id, 'shipping_price_net' => 10, 'terms' => 'Dodanie do 14 dní', 'valid_until' => today()->addDays(14)->toDateString()], $extra);
    }

    public function test_staff_quote_gets_number_and_draft_stays_hidden_from_customer(): void
    {
        $q = $this->create();
        $this->assertMatchesRegularExpression('/^CP-'.now()->year.'-0001$/', $q['number']);
        $this->assertSame('draft', $q['status']);
        $this->assertSame('staff', $q['source']);

        $this->putJson('/api/sales-quotes/'.$q['id'], $this->payload(['valid_until' => null, 'shipping_method_id' => null, 'items' => [['variant_id' => $this->variant->id, 'quantity' => 1, 'price_net' => 10]]]))
            ->assertOk()->assertJsonPath('has_draft', true)->assertJsonPath('versions.0.sent_at', null);

        $quote = SalesQuote::findOrFail($q['id']);
        $this->getJson('/api/public-quotes/'.$quote->uuid, ['X-Sales-Token' => $quote->shareToken()])->assertOk()
            ->assertJsonCount(0, 'versions')->assertJsonMissingPath('internal_note');

        // Pod minimálny odber sa koncept uložiť dá, odoslať nie.
        $this->postJson('/api/sales-quotes/'.$q['id'].'/offer', $this->payload(['items' => [['variant_id' => $this->variant->id, 'quantity' => 1, 'price_net' => 10]]]))
            ->assertUnprocessable()->assertJsonValidationErrors('items.0.quantity');
        $this->deleteJson('/api/sales-quotes/'.$q['id'].'/draft')->assertOk()->assertJsonPath('has_draft', false);
    }

    public function test_offer_computes_net_prices_and_emails_customer(): void
    {
        $q = $this->create();
        $res = $this->postJson('/api/sales-quotes/'.$q['id'].'/offer', $this->payload(['notify' => true]))->assertOk();

        // 10 × 9,00 + 2 × 99,99 = 289,98 bez DPH; zľava 5 % = 14,50; doprava 10.
        $totals = $res->json('versions.0.totals');
        $this->assertEquals(289.98, $totals['items_net']);
        $this->assertEquals(14.5, $totals['discount_net']);
        $this->assertEquals(285.48, $totals['net']);
        // S DPH: 10 × 11,07 + 2 × 122,99 = 356,68; zľava 17,83; doprava 12,30.
        $this->assertEquals(351.15, $totals['gross']);
        $this->assertEquals(round(351.15 - 285.48, 2), $totals['vat']);
        $this->assertStringEndsWith('product-placeholder.svg', $res->json('thumbs.'.$this->variant->id));
        $res->assertJsonPath('status', 'offered')->assertJsonPath('version', 1)->assertJsonPath('sent_to', 'obec@example.test');

        Notification::assertSentTo(new AnonymousNotifiable, QuoteOffered::class, function ($n, $channels, $notifiable) {
            $mail = $n->toMail($notifiable);
            $this->assertStringContainsString('CP-', $mail->subject);
            $html = (string) $mail->render();
            $this->assertStringContainsString('/ponuka/', $html);
            $this->assertStringContainsString('&amp;prijat=1', $html);
            $this->assertStringContainsString('ďakujeme za Váš dopyt', html_entity_decode($html));

            return $notifiable->routes['mail'] === ['obec@example.test' => 'Jana Nová'];
        });
    }

    public function test_accepted_quote_creates_order_with_custom_item_and_discount(): void
    {
        $q = $this->create();
        $this->postJson('/api/sales-quotes/'.$q['id'].'/offer', $this->payload())->assertOk();
        $quote = SalesQuote::findOrFail($q['id']);

        $this->getJson('/api/public-quotes/'.$quote->uuid, ['X-Sales-Token' => $quote->shareToken()])->assertOk()->assertJsonCount(1, 'versions');
        $this->assertNotNull($quote->fresh()->viewed_at);

        $uuid = $this->postJson('/api/public-quotes/'.$quote->uuid.'/accept', ['version' => 1, 'name' => 'Jana Nová', 'confirm' => true], ['X-Sales-Token' => $quote->shareToken()])
            ->assertOk()->json('order_uuid');
        $order = Order::where('uuid', $uuid)->firstOrFail();
        $custom = $order->orderProducts->firstWhere('is_custom', true);
        $this->assertNull($custom->product_id);
        $this->assertSame('Zástava s erbom obce', $custom->product_snapshot['name']);
        $this->assertEquals(122.99, $custom->price);
        $this->assertEquals(5, $order->price_adjustment['value']);
        $this->assertEquals($quote->fresh()->total_gross, round($order->priceSum() + $order->adjustmentAmount() + (float) $order->shipping_price, 2));
        $this->assertStringContainsString('150 × 300 cm', $order->note);
        $this->assertSame('accepted', $quote->fresh()->status);
    }

    public function test_index_filters_by_status_validity_and_search(): void
    {
        $a = $this->create();
        $this->postJson('/api/sales-quotes/'.$a['id'].'/offer', $this->payload(['valid_until' => today()->addDays(3)->toDateString()]))->assertOk();
        $b = $this->postJson('/api/sales-quotes', ['customer' => ['company' => 'Škola Nitra', 'name' => 'Peter'] + $this->customer])->assertCreated()->json();
        $old = $this->create();
        SalesQuote::whereKey($old['id'])->update(['status' => 'offered', 'version' => 1, 'valid_until' => today()->subDay()]);

        $this->getJson('/api/sales-quotes?status=expiring')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $a['id']);
        $this->getJson('/api/sales-quotes?status=expired')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.is_expired', true);
        $this->getJson('/api/sales-quotes?status=offered')->assertOk()->assertJsonCount(1, 'data');
        $this->getJson('/api/sales-quotes?search=Škola')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $b['id']);
        $this->getJson('/api/sales-quotes?search='.$a['number'])->assertOk()->assertJsonCount(1, 'data');
    }

    private function offered(array $extra = []): SalesQuote
    {
        $q = $this->create();
        $this->postJson('/api/sales-quotes/'.$q['id'].'/offer', $this->payload($extra))->assertOk();

        return SalesQuote::findOrFail($q['id']);
    }

    public function test_customer_chooses_quantity_variant_on_acceptance(): void
    {
        $items = $this->payload()['items'];
        $items[0]['alternatives'] = [['quantity' => 50, 'price_net' => 8], ['quantity' => 100, 'price_net' => 7]];
        $quote = $this->offered(['items' => $items]);
        $this->assertEquals(7.2, $quote->sentVersions()->first()->items[0]['alternatives'][0]['unit_net']);

        $this->postJson('/api/public-quotes/'.$quote->uuid.'/accept', ['version' => 1, 'name' => 'Jana', 'confirm' => true, 'choices' => [5 => 0]], ['X-Sales-Token' => $quote->shareToken()])
            ->assertUnprocessable();
        $uuid = $this->postJson('/api/public-quotes/'.$quote->uuid.'/accept', ['version' => 1, 'name' => 'Jana', 'confirm' => true, 'choices' => [0 => 1]], ['X-Sales-Token' => $quote->shareToken()])
            ->assertOk()->json('order_uuid');
        $line = Order::where('uuid', $uuid)->firstOrFail()->orderProducts->firstWhere('is_custom', false);
        $this->assertSame(100, (int) $line->quantity);
        // 7 € − 10 % = 6,30 bez DPH → 7,75 s DPH
        $this->assertEquals(7.75, $line->price);
        $this->assertEquals(775, $line->total);
        $this->assertNotEquals(351.15, $quote->fresh()->total_gross);
    }

    public function test_pdf_is_available_to_staff_customer_and_attached_to_email(): void
    {
        $quote = $this->offered(['notify' => true]);
        $res = $this->get('/api/sales-quotes/'.$quote->id.'/pdf')->assertOk()->assertHeader('Content-Type', 'application/pdf');
        $this->assertStringStartsWith('%PDF', $res->getContent());
        $this->get('/api/public-quotes/'.$quote->uuid.'/pdf')->assertNotFound();
        $this->get('/api/public-quotes/'.$quote->uuid.'/pdf', ['X-Sales-Token' => $quote->shareToken()])->assertOk()
            ->assertHeader('Content-Disposition', 'attachment; filename="Cenova-ponuka-'.$quote->number.'.pdf"');

        Notification::assertSentTo(new AnonymousNotifiable, QuoteOffered::class, function ($n, $channels, $notifiable) use ($quote) {
            $mail = $n->toMail($notifiable);

            return $mail->rawAttachments[0]['name'] === 'Cenova-ponuka-'.$quote->number.'.pdf';
        });
    }

    public function test_rejection_and_conversation(): void
    {
        $quote = $this->offered();
        $token = $quote->shareToken();
        $admin = User::role('super-admin')->first();

        $this->postJson('/api/public-quotes/'.$quote->uuid.'/messages', ['name' => 'Jana', 'body' => 'Dá sa dodať do piatku?', 'kind' => 'change_request'], ['X-Sales-Token' => $token])
            ->assertOk()->assertJsonPath('messages.0.kind', 'change_request');
        Notification::assertSentTo($admin, QuoteMessageReceived::class);
        $this->getJson('/api/sales-quotes?status=unread')->assertJsonCount(1, 'data')->assertJsonPath('data.0.unread_messages', 1);
        $this->getJson('/api/sales-quotes/'.$quote->id)->assertOk();
        $this->getJson('/api/sales-quotes?status=unread')->assertJsonCount(0, 'data');

        $this->postJson('/api/sales-quotes/'.$quote->id.'/messages', ['body' => 'Áno, do piatku stihneme.'])->assertOk()->assertJsonCount(2, 'messages');
        Notification::assertSentTo(new AnonymousNotifiable, QuoteMessageSent::class);

        $this->postJson('/api/public-quotes/'.$quote->uuid.'/reject', ['name' => 'Jana', 'reason' => 'Príliš drahé'], ['X-Sales-Token' => $token])
            ->assertOk()->assertJsonPath('status', 'rejected')->assertJsonPath('rejection_reason', 'Príliš drahé');
        $this->postJson('/api/public-quotes/'.$quote->uuid.'/accept', ['version' => 1, 'name' => 'Jana', 'confirm' => true], ['X-Sales-Token' => $token])->assertConflict();

        // Po odmietnutí môže obsluha poslať novú verziu.
        $this->postJson('/api/sales-quotes/'.$quote->id.'/offer', $this->payload(['version' => 1]))->assertOk()->assertJsonPath('status', 'offered')->assertJsonPath('version', 2);
    }

    public function test_closed_quote_cannot_be_edited(): void
    {
        $q = $this->create();
        $this->postJson('/api/sales-quotes/'.$q['id'].'/withdraw')->assertOk();
        $this->putJson('/api/sales-quotes/'.$q['id'], $this->payload())->assertConflict();
    }
}
