<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\User;
use Laravel\Sanctum\Sanctum;
use App\Models\CustomerAddress;
use App\Models\Order;
use App\Models\Product;
use App\Notifications\OrderDeliveryAddressChanged;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * Doručovacia adresa objednávky — iná než sídlo zákazníka.
 */
class DeliveryAddressTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Notification::fake();
    }

    private function customerPayload(): array
    {
        return [
            'company' => 'Obec Testovce',
            'name' => 'Ján Testovací',
            'email' => 'jan@example.com',
            'phone' => '+421900123456',
            'street' => 'Obecná 1',
            'postcode' => '01001',
            'city' => 'Žilina',
        ];
    }

    private function deliveryPayload(array $overrides = []): array
    {
        return array_merge([
            'company'  => 'Základná škola Testovce',
            'name'     => 'Mgr. Eva Riaditeľka',
            'street'   => 'Školská 12',
            'postcode' => '949 01',
            'city'     => 'Nitra',
            'phone'    => '+421 905 111 222',
            'note'     => 'Doručiť na sekretariát',
        ], $overrides);
    }

    private function makeProduct(string $code = 'VSR-100', float $price = 25.50): Product
    {
        $product = Product::create([
            'name' => 'Vlajka SR 100x150',
            'code' => $code,
            'vat' => 20,
            'published' => 1,
        ]);

        $product->variants()->create([
            'code' => $code . '-DEF',
            'name' => '100 × 150 cm',
            'price' => $price,
            'min_order' => 1,
            'is_default' => true,
            'published' => 1,
        ]);

        return $product->load('defaultVariant');
    }

    private function placeOrder(array $payload = []): Order
    {
        $product = $this->makeProduct();

        $this->postCheckout(array_merge([
            'customer' => $this->customerPayload(),
            'orderProducts' => [
                ['id' => $product->id, 'input_order' => 2],
            ],
        ], $payload))->assertOk();

        return Order::firstOrFail();
    }

    public function test_checkout_stores_delivery_address_separate_from_billing(): void
    {
        $order = $this->placeOrder(['delivery' => $this->deliveryPayload()]);

        $this->assertTrue($order->hasCustomDelivery());
        $this->assertSame('Školská 12', $order->delivery_street);
        // PSČ sa ukladá bez medzier, aby sa dalo porovnávať a triediť.
        $this->assertSame('94901', $order->delivery_postcode);
        $this->assertSame('+421905111222', $order->delivery_phone);

        $snapshot = $order->deliverySnapshot();
        $this->assertTrue($snapshot['is_custom']);
        $this->assertSame('949 01', $snapshot['postcode']);
        $this->assertSame('Základná škola Testovce', $snapshot['company']);

        // Fakturačná adresa zákazníka ostáva nedotknutá.
        $this->assertSame('Obecná 1', $order->customer->street);
    }

    public function test_checkout_without_delivery_falls_back_to_billing_address(): void
    {
        $order = $this->placeOrder();

        $this->assertFalse($order->hasCustomDelivery());

        $snapshot = $order->deliverySnapshot();
        $this->assertFalse($snapshot['is_custom']);
        $this->assertSame('Obecná 1', $snapshot['street']);
        $this->assertSame('Žilina', $snapshot['city']);
    }

    /**
     * Prázdny `delivery` posiela formulár aj vtedy, keď zákazník zaškrtnutie
     * zase zrušil — nesmie z toho byť validačná chyba ani prázdny odtlačok.
     */
    public function test_empty_delivery_payload_is_ignored(): void
    {
        $order = $this->placeOrder([
            'delivery' => ['street' => '', 'postcode' => '', 'city' => '', 'company' => ''],
        ]);

        $this->assertFalse($order->hasCustomDelivery());
    }

    public function test_partial_delivery_address_is_rejected(): void
    {
        $product = $this->makeProduct();

        $this->postCheckout([
            'customer' => $this->customerPayload(),
            'orderProducts' => [['id' => $product->id, 'input_order' => 1]],
            'delivery' => ['city' => 'Nitra'],
        ])->assertStatus(422)->assertJsonValidationErrors(['delivery.street', 'delivery.postcode']);

        $this->assertSame(0, Order::count());
    }

    /**
     * Adresa zhodná so sídlom nie je „iná adresa" — objednávka nesmie niesť
     * odtlačok, ktorý sa časom rozíde s opravenými údajmi zákazníka.
     */
    public function test_delivery_address_equal_to_billing_is_not_stored_as_custom(): void
    {
        $order = $this->placeOrder([
            'delivery' => ['street' => 'Obecná 1', 'postcode' => '010 01', 'city' => 'Žilina'],
        ]);

        $this->assertFalse($order->hasCustomDelivery());
    }

    /** Ten istý dom, iný príjemca — to odtlačok potrebuje. */
    public function test_same_street_with_different_recipient_is_stored(): void
    {
        $order = $this->placeOrder([
            'delivery' => [
                'street' => 'Obecná 1',
                'postcode' => '01001',
                'city' => 'Žilina',
                'name' => 'k rukám p. Nováka',
            ],
        ]);

        $this->assertTrue($order->hasCustomDelivery());
        $this->assertSame('k rukám p. Nováka', $order->delivery_name);
    }

    public function test_save_address_adds_it_to_customer_address_book_once(): void
    {
        $staff = User::factory()->create();
        $staff->assignRole('super-admin');
        Sanctum::actingAs($staff);
        $this->placeOrder([
            'delivery' => $this->deliveryPayload(['save_address' => true, 'label' => 'Škola']),
        ]);

        $customer = Customer::firstOrFail();
        $this->assertSame(1, $customer->addresses()->count());

        $address = $customer->addresses()->first();
        $this->assertSame('Škola', $address->label);
        $this->assertTrue($address->is_default);
        $this->assertSame(Order::first()->customer_address_id, $address->id);

        // Druhá objednávka na tú istú adresu adresár nezduplikuje.
        $product = $this->makeProduct('VSR-200', 12.00);
        $this->postCheckout([
            'customer' => $this->customerPayload() + ['id' => $customer->id],
            'orderProducts' => [['id' => $product->id, 'input_order' => 1]],
            'delivery' => $this->deliveryPayload(['save_address' => true]),
        ])->assertOk();

        $this->assertSame(1, $customer->addresses()->count());
    }

    public function test_public_order_detail_exposes_delivery_address(): void
    {
        $order = $this->placeOrder(['delivery' => $this->deliveryPayload()]);

        $this->getJson("/api/public-orders/{$order->uuid}")
            ->assertOk()
            ->assertJsonPath('data.delivery.is_custom', true)
            ->assertJsonPath('data.delivery.street', 'Školská 12')
            ->assertJsonPath('data.can_edit_delivery', true);
    }

    public function test_delivery_address_can_be_changed_with_token_from_email(): void
    {
        $order = $this->placeOrder();

        $this->putJson("/api/public-orders/{$order->uuid}/delivery-address", [
            'token' => $order->delivery_token,
            'delivery' => $this->deliveryPayload(),
        ])->assertOk()->assertJsonPath('data.delivery.city', 'Nitra');

        $order->refresh();
        $this->assertTrue($order->hasCustomDelivery());
        $this->assertNotNull($order->delivery_changed_at);
        $this->assertSame('zákazník (odkaz z e-mailu)', $order->delivery_changed_by);

        Notification::assertSentTo($order, OrderDeliveryAddressChanged::class);
    }

    /**
     * Odkaz na detail objednávky sa preposiela — čítanie objednávky nesmie
     * znamenať právo prepísať, kam sa pošle.
     */
    public function test_delivery_address_cannot_be_changed_without_valid_token(): void
    {
        $order = $this->placeOrder();

        $this->putJson("/api/public-orders/{$order->uuid}/delivery-address", [
            'delivery' => $this->deliveryPayload(),
        ])->assertNotFound();

        $this->putJson("/api/public-orders/{$order->uuid}/delivery-address", [
            'token' => 'nespravny-token',
            'delivery' => $this->deliveryPayload(),
        ])->assertNotFound();

        $this->assertFalse($order->refresh()->hasCustomDelivery());
    }

    public function test_expired_token_is_refused(): void
    {
        $order = $this->placeOrder();
        $order->forceFill(['delivery_token_expires_at' => now()->subDay()])->save();

        $this->getJson("/api/public-orders/{$order->uuid}/delivery-address?token={$order->delivery_token}")
            ->assertNotFound();
    }

    /** Po expedícii je adresa história — balík je na ceste. */
    public function test_delivery_address_is_locked_after_shipping(): void
    {
        $order = $this->placeOrder();
        $orderProduct = $order->orderProducts()->firstOrFail();

        $shipping = $order->shippings()->create([]);
        $shipping->stocks()->create([
            'order_id' => $order->id,
            'order_product_id' => $orderProduct->id,
            'product_id' => $orderProduct->product_id,
            'product_variant_id' => $orderProduct->product_variant_id,
            'quantity' => $orderProduct->quantity,
        ]);

        $this->assertFalse($order->refresh()->canEditDelivery());

        $this->putJson("/api/public-orders/{$order->uuid}/delivery-address", [
            'token' => $order->delivery_token,
            'delivery' => $this->deliveryPayload(),
        ])->assertStatus(409);

        $this->assertFalse($order->refresh()->hasCustomDelivery());
    }

    /** Adresár patrí zákazníkovi; objednávka si adresu odfotí. */
    public function test_editing_address_book_does_not_rewrite_past_orders(): void
    {
        $order = $this->placeOrder([
            'delivery' => $this->deliveryPayload(['save_address' => true]),
        ]);

        $address = CustomerAddress::firstOrFail();
        $address->update(['street' => 'Iná 99', 'city' => 'Košice']);

        $order->refresh();
        $this->assertSame('Školská 12', $order->delivery_street);
        $this->assertSame('Nitra', $order->delivery_city);
    }

    public function test_staff_can_pick_saved_address_by_id(): void
    {
        $staff = User::factory()->create();
        $staff->assignRole('super-admin');
        Sanctum::actingAs($staff);
        $customer = Customer::create([
            'company' => 'Obec Testovce',
            'slug' => 'obec-testovce',
            'street' => 'Obecná 1',
            'postcode' => '01001',
            'city' => 'Žilina',
        ]);

        $address = $customer->addresses()->create([
            'label' => 'Kultúrny dom',
            'street' => 'Námestie 5',
            'postcode' => '01001',
            'city' => 'Žilina',
        ]);

        $product = $this->makeProduct();

        $this->postCheckout([
            'customer' => $this->customerPayload() + ['id' => $customer->id],
            'orderProducts' => [['id' => $product->id, 'input_order' => 1]],
            'customer_address_id' => $address->id,
        ])->assertOk();

        $order = Order::firstOrFail();
        $this->assertSame($address->id, $order->customer_address_id);
        $this->assertSame('Námestie 5', $order->delivery_street);
    }

    /**
     * E-maily musia adresu naozaj vypísať — blade sa inak pokazí ticho a
     * zákazník dostane potvrdenie bez toho, kam mu tovar ide.
     */
    public function test_emails_render_the_delivery_address(): void
    {
        $order = $this->placeOrder(['delivery' => $this->deliveryPayload()]);
        $order->load(['customer', 'orderProducts.product', 'shippingMethod', 'paymentMethod']);

        $confirmation = view('emails.orderConfirmation', ['order' => $order])->render();

        $this->assertStringContainsString('Adresa doručenia', $confirmation);
        $this->assertStringContainsString('Školská 12', $confirmation);
        $this->assertStringContainsString('949 01 Nitra', $confirmation);
        // Fakturačná adresa sa ukáže vedľa, keď sa od doručovacej líši.
        $this->assertStringContainsString('Obecná 1', $confirmation);
        $this->assertStringContainsString($order->delivery_token, $confirmation);

        $changed = view('emails.orderDeliveryAddressChanged', [
            'order' => $order,
            'previous' => ['company' => 'Obec Testovce', 'name' => null, 'street' => 'Obecná 1', 'postcode' => '010 01', 'city' => 'Žilina', 'phone' => null, 'note' => null],
            'current' => $order->deliverySnapshot(),
        ])->render();

        $this->assertStringContainsString('Obecná 1', $changed);
        $this->assertStringContainsString('Školská 12', $changed);
    }

    /** Cudzí adresár sa podstrčiť nedá — id patriace inému zákazníkovi sa ignoruje. */
    public function test_address_of_another_customer_is_ignored(): void
    {
        $other = Customer::create([
            'company' => 'Cudzia firma',
            'slug' => 'cudzia-firma',
            'street' => 'Tajná 1',
            'postcode' => '81101',
            'city' => 'Bratislava',
        ]);

        $foreign = $other->addresses()->create([
            'street' => 'Tajná 1',
            'postcode' => '81101',
            'city' => 'Bratislava',
        ]);

        $this->postCheckout([
            'customer' => $this->customerPayload(),
            'orderProducts' => [['id' => $this->makeProduct()->id, 'input_order' => 1]],
            'customer_address_id' => $foreign->id,
        ])->assertUnprocessable()->assertJsonValidationErrors('customer_address_id');
        $this->assertDatabaseCount('orders', 0);
    }
}
