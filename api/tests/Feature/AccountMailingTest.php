<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\OrderEmail;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Support\OrderThread;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * Emailing v Accounte (ACCOUNT_MAILING): udalosti objednávok idú do Accountu
 * a staré odkazy z kampaní zastáv sa presmerujú tam.
 */
class AccountMailingTest extends TestCase
{
    use RefreshDatabase;

    private ProductVariant $variant;

    private array $customer = ['company' => 'Test buyer', 'name' => 'Buyer', 'email' => 'buyer@example.test', 'phone' => '0900123456', 'street' => 'Street 1', 'city' => 'Nitra', 'postcode' => '94901'];

    protected function setUp(): void
    {
        parent::setUp();

        Mail::fake();

        config()->set('account.url', 'http://account.test');
        config()->set('account.public_url', 'https://account.example');
        config()->set('account.token', 'acc_test');
        config()->set('account.webhook_secret', 'whsec_test');
        config()->set('account.mailing', true);

        Http::fake([
            'account.test/api/v1/mailing/events' => Http::response(['data' => ['queued' => 1]], 202),
            'account.test/api/v1/mailing/events/cancel' => Http::response(['data' => ['skipped' => 1]]),
            'account.test/api/v1/mailing/triggers' => Http::response(['data' => []]),
            'account.test/api/v1/mailing/tags' => Http::response(['data' => []]),
            'account.test/api/v1/mailing/contacts/remove' => Http::response(['data' => ['removed' => 1]]),
            'account.test/api/v1/mailing/contacts' => Http::response(['data' => ['added' => 1, 'updated' => 0]], 201),
        ]);

        $p = Product::create(['name' => 'Flag', 'code' => 'FLAG', 'published' => true, 'vat' => 23, 'unit_value' => 'ks']);
        $this->variant = $p->variants()->create(['name' => 'Large', 'code' => 'FLAG-L', 'published' => true, 'price' => 12, 'is_default' => true, 'min_order' => 1]);
    }

    public function test_nova_objednavka_nahlasi_udalost_do_accountu(): void
    {
        $order = $this->order();

        Http::assertSent(fn (Request $request) => $request->url() === 'http://account.test/api/v1/mailing/events'
            && $request['event'] === 'order_created'
            && $request['email'] === 'buyer@example.test'
            && $request['ref']['type'] === 'order'
            && $request['ref']['id'] === $order->uuid
            && $request['dedupe_key'] === 'order:'.$order->uuid
            && $request['thread_id'] === OrderThread::id($order)
            && $request['variables']['firma'] === 'Test buyer');
    }

    public function test_bez_emailingu_v_accounte_sa_udalosti_neposielaju(): void
    {
        config()->set('account.mailing', false);

        $order = $this->order();
        $order->update(['status' => OrderStatus::Cancelled]);

        Http::assertNotSent(fn (Request $request) => str_contains($request->url(), '/api/v1/mailing/'));
    }

    public function test_storno_objednavky_zrusi_cakajuce_maily_v_accounte(): void
    {
        $order = $this->order();

        $order->update(['status' => OrderStatus::Cancelled]);

        Http::assertSent(fn (Request $request) => $request->url() === 'http://account.test/api/v1/mailing/events/cancel'
            && $request['ref']['id'] === $order->uuid
            && $request['except'] === ['order_cancelled']);
    }

    public function test_odpoved_vo_vlakne_z_accountu_sa_zapise_do_historie_objednavky(): void
    {
        $order = $this->order();

        $this->webhook('mailing.delivery.sent', [
            'delivery' => ['email' => 'buyer@example.test', 'ref' => ['type' => 'order', 'id' => $order->uuid], 'status' => 'sent'],
            'campaign' => ['id' => 1, 'name' => 'Poďakovanie', 'reply_in_thread' => true],
            'subject' => 'Re: Objednávka',
            'html' => '<p>Ďakujeme</p>',
        ])->assertOk();

        $email = OrderEmail::where('order_id', $order->id)->where('type', 'Emailing')->sole();
        $this->assertSame('sent', $email->status);
        $this->assertSame('Re: Objednávka', $email->subject);
    }

    public function test_stare_odkazy_z_kampani_presmeruju_do_accountu(): void
    {
        $token = str_repeat('a', 64);

        $this->post("/api/emailing/unsubscribe/{$token}")->assertStatus(307)->assertRedirect("https://account.example/m/u/{$token}");
        $this->get("/api/emailing/link/{$token}")->assertRedirect("https://account.example/m/c/{$token}");
        $this->get("/api/emailing/image/{$token}.gif")->assertRedirect("https://account.example/m/o/{$token}.gif");
    }

    public function test_registracia_udalosti_posle_vsetky_spustace(): void
    {
        $this->artisan('emailing:register-triggers')->assertSuccessful();

        Http::assertSent(fn (Request $request) => $request->url() === 'http://account.test/api/v1/mailing/triggers'
            && collect($request['triggers'])->pluck('key')->all() === ['order_created', 'shipping_dispatched', 'order_completed', 'order_cancelled']
            && $request['triggers'][0]['quote_kind'] === 'order.OrderCreated');
    }

    public function test_registracia_posle_aj_stitky_zakaznikov(): void
    {
        $this->artisan('emailing:register-triggers')->assertSuccessful();

        Http::assertSent(fn (Request $request) => $request->url() === 'http://account.test/api/v1/mailing/tags'
            && collect($request['tags'])->pluck('key')->all() === ['customer', 'municipality', 'school', 'company', 'person', 'account']);
    }

    public function test_objednavka_posle_zakaznika_do_adresara_so_suhlasom(): void
    {
        $order = $this->order();
        $order->update(['status' => OrderStatus::Processing]);

        Http::assertSent(fn (Request $request) => $request->url() === 'http://account.test/api/v1/mailing/contacts'
            && $request['contacts'][0]['email'] === 'buyer@example.test'
            && in_array('customer', $request['contacts'][0]['tags'], true)
            && in_array($order->customer->type->value, $request['contacts'][0]['tags'], true)
            && $request['contacts'][0]['attributes']['firma'] === 'Test buyer'
            && filled($request['contacts'][0]['permission_note']));
    }

    public function test_zmazany_zakaznik_zmizne_z_adresara(): void
    {
        $customer = $this->order()->customer;

        $customer->delete();

        Http::assertSent(fn (Request $request) => $request->url() === 'http://account.test/api/v1/mailing/contacts/remove'
            && $request['emails'] === ['buyer@example.test']);
    }

    public function test_doplnenie_adresara_posle_vsetkych_zakaznikov(): void
    {
        config()->set('account.mailing', false);
        $this->order();
        config()->set('account.mailing', true);

        $this->artisan('account:sync-mailing-contacts', ['--dry-run' => true])
            ->expectsOutputToContain('Poslalo by sa kontaktov: 1')->assertSuccessful();
        Http::assertNothingSent();

        $this->artisan('account:sync-mailing-contacts')->assertSuccessful();

        Http::assertSent(fn (Request $request) => $request->url() === 'http://account.test/api/v1/mailing/contacts'
            && $request['contacts'][0]['email'] === 'buyer@example.test');
    }

    private function order(): Order
    {
        $this->postCheckout(['customer' => $this->customer, 'orderProducts' => [['id' => $this->variant->product_id, 'variant_id' => $this->variant->id, 'input_order' => 2]]])->assertOk();

        return Order::latest('id')->firstOrFail();
    }

    /** @param array<string, mixed> $data */
    private function webhook(string $event, array $data): \Illuminate\Testing\TestResponse
    {
        $body = json_encode(['id' => 'evt-'.uniqid(), 'event' => $event, 'data' => $data]);
        $timestamp = time();

        return $this->call('POST', '/api/webhooks/account', server: [
            'HTTP_X_ACCOUNTS_TIMESTAMP' => $timestamp,
            'HTTP_X_ACCOUNTS_SIGNATURE' => hash_hmac('sha256', $timestamp.'.'.$body, 'whsec_test'),
            'CONTENT_TYPE' => 'application/json',
            'HTTP_ACCEPT' => 'application/json',
        ], content: $body);
    }
}
