<?php

namespace Tests\Feature;

use App\Models\Coupon;
use App\Models\Customer;
use App\Models\Order;
use App\Models\PaymentMethod;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\ShippingMethod;
use App\Models\User;
use App\Notifications\OrderCreated;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class CheckoutTest extends TestCase
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
            'company' => 'Testovacia firma s.r.o.',
            'name' => 'Ján Testovací',
            'email' => 'jan@example.com',
            'phone' => '+421900123456',
            'street' => 'Hlavná 1',
            'postcode' => '01001',
            'city' => 'Žilina',
        ];
    }

    /**
     * Cena a sklad žijú na variante — produkt bez variantu sa nedá objednať.
     */
    private function makeProduct(float $price = 25.50): Product
    {
        $product = Product::create([
            'name' => 'Vlajka SR 100x150',
            'code' => 'VSR-100',
            'vat' => 20,
            'published' => 1,
        ]);

        $product->variants()->create([
            'code' => 'VSR-100-100X150',
            'name' => '100 × 150 cm',
            'price' => $price,
            'min_order' => 1,
            'is_default' => true,
            'published' => 1,
        ]);

        return $product->load('defaultVariant');
    }

    private function variant(Product $product): ProductVariant
    {
        return $product->defaultVariant()->firstOrFail();
    }

    public function test_order_uses_database_price_not_client_price(): void
    {
        $product = $this->makeProduct(25.50);

        $response = $this->postJson('/api/checkouts', [
            'customer' => $this->customerPayload(),
            'orderProducts' => [
                ['id' => $product->id, 'input_order' => 2, 'active_price' => 0.01],
            ],
        ]);

        $response->assertOk()->assertJsonStructure(['uuid', 'serial_number']);

        $this->assertDatabaseHas('order_products', [
            'product_id' => $product->id,
            'quantity' => 2,
            'price' => 25.50,
            'total' => 51.00,
        ]);
    }

    public function test_order_uses_sale_price_when_set(): void
    {
        $product = $this->makeProduct(25.50);
        $this->variant($product)->update(['sale_price' => 19.99]);

        $this->postJson('/api/checkouts', [
            'customer' => $this->customerPayload(),
            'orderProducts' => [
                ['id' => $product->id, 'input_order' => 1, 'active_price' => 25.50],
            ],
        ])->assertOk();

        $this->assertDatabaseHas('order_products', [
            'product_id' => $product->id,
            'price' => 19.99,
        ]);
    }

    public function test_invalid_coupon_is_rejected_and_no_order_is_created(): void
    {
        $product = $this->makeProduct();

        Coupon::create([
            'code' => 'EXPIRED',
            'type' => 'percent',
            'value' => 10,
            'active' => true,
            'valid_to' => now()->subDay(),
        ]);

        $this->postJson('/api/checkouts', [
            'customer' => $this->customerPayload(),
            'orderProducts' => [
                ['id' => $product->id, 'input_order' => 1, 'active_price' => 25.50],
            ],
            'coupon_code' => 'EXPIRED',
        ])->assertStatus(422)->assertJsonValidationErrors('coupon_code');

        $this->assertSame(0, Order::count());
    }

    public function test_valid_coupon_discount_is_calculated_from_database_prices(): void
    {
        $product = $this->makeProduct(100.00);

        Coupon::create([
            'code' => 'ZLAVA10',
            'type' => 'percent',
            'value' => 10,
            'active' => true,
        ]);

        $this->postJson('/api/checkouts', [
            'customer' => $this->customerPayload(),
            'orderProducts' => [
                // Klient posiela falošnú cenu 1 € — zľava sa musí rátať zo 100 €.
                ['id' => $product->id, 'input_order' => 1, 'active_price' => 1.00],
            ],
            'coupon_code' => 'ZLAVA10',
        ])->assertOk();

        $order = Order::first();
        $this->assertEquals(10.00, (float) $order->discount_amount);
        $this->assertEquals(1, Coupon::first()->used_count);
    }

    public function test_coupon_over_usage_limit_is_rejected(): void
    {
        $product = $this->makeProduct(50.00);

        Coupon::create([
            'code' => 'LIMIT1',
            'type' => 'percent',
            'value' => 10,
            'active' => true,
            'usage_limit' => 1,
            'used_count' => 1,
        ]);

        $this->postJson('/api/checkouts', [
            'customer' => $this->customerPayload(),
            'orderProducts' => [
                ['id' => $product->id, 'input_order' => 1, 'active_price' => 50.00],
            ],
            'coupon_code' => 'LIMIT1',
        ])->assertStatus(422)->assertJsonValidationErrors('coupon_code');

        $this->assertSame(0, Order::count());
    }

    public function test_quantity_below_min_order_is_bumped_to_min_order(): void
    {
        $product = $this->makeProduct(10.00);
        $this->variant($product)->update(['min_order' => 5]);

        $this->postJson('/api/checkouts', [
            'customer' => $this->customerPayload(),
            'orderProducts' => [
                ['id' => $product->id, 'input_order' => 2, 'active_price' => 10.00],
            ],
        ])->assertOk();

        $this->assertDatabaseHas('order_products', [
            'product_id' => $product->id,
            'quantity' => 5,
        ]);
    }

    public function test_absurd_quantity_is_rejected(): void
    {
        $product = $this->makeProduct();

        $this->postJson('/api/checkouts', [
            'customer' => $this->customerPayload(),
            'orderProducts' => [
                ['id' => $product->id, 'input_order' => 999999999, 'active_price' => 25.50],
            ],
        ])->assertStatus(422);

        $this->assertSame(0, Order::count());
    }

    public function test_unpublished_product_cannot_be_ordered(): void
    {
        $product = $this->makeProduct();
        $product->update(['published' => 0]);

        $this->postJson('/api/checkouts', [
            'customer' => $this->customerPayload(),
            'orderProducts' => [
                ['id' => $product->id, 'input_order' => 1, 'active_price' => 25.50],
            ],
        ])->assertStatus(422);

        $this->assertSame(0, Order::count());
    }

    public function test_staff_can_order_unpublished_product(): void
    {
        $product = $this->makeProduct();
        $product->update(['published' => 0]);

        Role::findOrCreate('super-admin', 'web');
        $staff = User::factory()->create();
        $staff->assignRole('super-admin');
        Sanctum::actingAs($staff);

        $this->postJson('/api/checkouts', [
            'customer' => $this->customerPayload(),
            'orderProducts' => [
                ['id' => $product->id, 'input_order' => 1, 'active_price' => 25.50],
            ],
        ])->assertOk();

        $this->assertDatabaseHas('order_products', ['product_id' => $product->id]);
    }

    public function test_staff_can_suppress_customer_notification(): void
    {
        $product = $this->makeProduct();

        Role::findOrCreate('super-admin', 'web');
        $staff = User::factory()->create();
        $staff->assignRole('super-admin');
        Sanctum::actingAs($staff);

        $this->postJson('/api/checkouts', [
            'customer' => $this->customerPayload(),
            'orderProducts' => [
                ['id' => $product->id, 'input_order' => 1, 'active_price' => 25.50],
            ],
            'notify_customer' => false,
        ])->assertOk();

        Notification::assertNotSentTo(Customer::firstOrFail(), OrderCreated::class);
    }

    public function test_public_checkout_cannot_suppress_customer_notification(): void
    {
        $product = $this->makeProduct();

        $this->postJson('/api/checkouts', [
            'customer' => $this->customerPayload(),
            'orderProducts' => [
                ['id' => $product->id, 'input_order' => 1, 'active_price' => 25.50],
            ],
            'notify_customer' => false,
        ])->assertOk();

        Notification::assertSentTo(Customer::firstOrFail(), OrderCreated::class);
    }

    public function test_unknown_product_is_rejected(): void
    {
        $this->postJson('/api/checkouts', [
            'customer' => $this->customerPayload(),
            'orderProducts' => [
                ['id' => 99999, 'input_order' => 1, 'active_price' => 5],
            ],
        ])->assertStatus(422);

        $this->assertSame(0, Order::count());
    }

    private function makeCustomerWithContact(): Customer
    {
        return Customer::create([
            'name' => 'Kontaktná osoba',
            'company' => 'Firma s.r.o.',
            'email' => 'tajny@example.com',
            'phone' => '+421911222333',
            'street' => 'Hlavná 1',
            'postcode' => '01001',
            'city' => 'Žilina',
            'ico' => '12345678',
        ]);
    }

    public function test_public_ico_lookup_does_not_leak_customer_contact(): void
    {
        $customer = $this->makeCustomerWithContact();

        // Verejná vetva ide do registra — externé API zamockujeme.
        Http::fake([
            'api.orsf.sk/*' => Http::response([
                'name' => 'Firma s.r.o.',
                'address' => ['city' => 'Žilina', 'postalCode' => '01001'],
            ], 200),
        ]);

        $response = $this->getJson('/api/checkouts/' . $customer->ico);

        $response->assertOk()->assertJsonPath('source', 'internet');

        // E-mail ani telefón zákazníka sa nesmú objaviť v odpovedi.
        $this->assertStringNotContainsString('tajny@example.com', $response->getContent());
        $this->assertStringNotContainsString('+421911222333', $response->getContent());
    }

    public function test_staff_ico_lookup_returns_customer_contact(): void
    {
        $customer = $this->makeCustomerWithContact();

        Role::findOrCreate('super-admin', 'web');
        $staff = User::factory()->create();
        $staff->assignRole('super-admin');
        Sanctum::actingAs($staff);

        $response = $this->getJson('/api/checkouts/' . $customer->ico);

        $response->assertOk()
            ->assertJsonPath('source', 'database')
            ->assertJsonPath('data.email', 'tajny@example.com')
            ->assertJsonPath('data.phone', $customer->fresh()->phone);
    }

    public function test_portal_customer_does_not_get_contact_prefill(): void
    {
        $customer = $this->makeCustomerWithContact();

        Http::fake([
            'api.orsf.sk/*' => Http::response(['name' => 'Firma s.r.o.'], 200),
        ]);

        // Prihlásený portálový zákazník (bez staff roly) nesmie dostať PII iného zákazníka.
        $portalUser = User::factory()->create(['customer_id' => $customer->id]);
        Sanctum::actingAs($portalUser);

        $response = $this->getJson('/api/checkouts/' . $customer->ico);

        $response->assertOk()->assertJsonPath('source', 'internet');
        $this->assertStringNotContainsString('tajny@example.com', $response->getContent());
    }

    public function test_fixed_coupon_discount_is_capped_at_cart_total(): void
    {
        $product = $this->makeProduct(30.00);

        Coupon::create([
            'code' => 'MINUS50',
            'type' => 'fixed',
            'value' => 50, // vyššie ako suma košíka (30 €)
            'active' => true,
        ]);

        $this->postJson('/api/checkouts', [
            'customer' => $this->customerPayload(),
            'orderProducts' => [
                ['id' => $product->id, 'input_order' => 1, 'active_price' => 30.00],
            ],
            'coupon_code' => 'MINUS50',
        ])->assertOk();

        // Zľava sa nesmie prevýšiť sumu košíka → 30 €, nie 50 €.
        $this->assertEquals(30.00, (float) Order::first()->discount_amount);
    }

    public function test_free_shipping_above_threshold(): void
    {
        $product = $this->makeProduct(100.00);

        $freeFrom = ShippingMethod::create([
            'name' => 'Kuriér',
            'price' => 5.00,
            'free_from_price' => 80.00,
            'active' => true,
        ]);

        $this->postJson('/api/checkouts', [
            'customer' => $this->customerPayload(),
            'orderProducts' => [
                ['id' => $product->id, 'input_order' => 1, 'active_price' => 100.00],
            ],
            'shipping_method_id' => $freeFrom->id,
        ])->assertOk();

        $this->assertEquals(0.0, (float) Order::first()->shipping_price);
    }

    public function test_public_order_grand_total_matches_components(): void
    {
        $product = $this->makeProduct(100.00);

        $shipping = ShippingMethod::create([
            'name' => 'Kuriér', 'price' => 4.00, 'active' => true,
        ]);
        $payment = PaymentMethod::create([
            'name' => 'Dobierka', 'fee' => 1.50, 'type' => 'cash_on_delivery', 'active' => true,
        ]);
        Coupon::create([
            'code' => 'ZLAVA10', 'type' => 'percent', 'value' => 10, 'active' => true,
        ]);

        $response = $this->postJson('/api/checkouts', [
            'customer' => $this->customerPayload(),
            'orderProducts' => [
                ['id' => $product->id, 'input_order' => 2, 'active_price' => 100.00],
            ],
            'shipping_method_id' => $shipping->id,
            'payment_method_id' => $payment->id,
            'coupon_code' => 'ZLAVA10',
        ])->assertOk();

        $uuid = $response->json('uuid');

        // subtotal 200 + doprava 4 + poplatok 1.5 - zľava 20 = 185.5
        $this->getJson('/api/public-orders/' . $uuid)
            ->assertOk()
            ->assertJsonPath('data.subtotal', 200)
            ->assertJsonPath('data.discount_amount', 20)
            ->assertJsonPath('data.grand_total', 185.5);
    }

    public function test_serial_number_is_not_reused_after_soft_delete(): void
    {
        $product = $this->makeProduct();

        $payload = [
            'customer' => $this->customerPayload(),
            'orderProducts' => [
                ['id' => $product->id, 'input_order' => 1, 'active_price' => 25.50],
            ],
        ];

        $this->postJson('/api/checkouts', $payload)->assertOk();
        $first = Order::latest('id')->first();

        // Zmazanie objednávky nesmie spôsobiť recykláciu sériového čísla.
        $first->delete();

        $this->postJson('/api/checkouts', $payload)->assertOk();
        $second = Order::latest('id')->first();

        $this->assertNotEquals($first->serial_number, $second->serial_number);
    }

    public function test_order_uses_price_of_selected_variant(): void
    {
        $product = $this->makeProduct(25.50);

        $bigger = $product->variants()->create([
            'code' => 'VSR-100-200X300',
            'name' => '200 × 300 cm',
            'price' => 64.00,
            'min_order' => 1,
            'published' => 1,
        ]);

        $this->postJson('/api/checkouts', [
            'customer' => $this->customerPayload(),
            'orderProducts' => [
                ['id' => $product->id, 'variant_id' => $bigger->id, 'input_order' => 1],
            ],
        ])->assertOk();

        $this->assertDatabaseHas('order_products', [
            'product_id'         => $product->id,
            'product_variant_id' => $bigger->id,
            'variant_label'      => '200 × 300 cm',
            'price'              => 64.00,
        ]);
    }

    public function test_unpublished_variant_cannot_be_ordered(): void
    {
        $product = $this->makeProduct(25.50);

        $hidden = $product->variants()->create([
            'code' => 'VSR-100-SKRYTY',
            'name' => 'Skrytý',
            'price' => 1.00,
            'min_order' => 1,
            'published' => 0,
        ]);

        $this->postJson('/api/checkouts', [
            'customer' => $this->customerPayload(),
            'orderProducts' => [
                ['id' => $product->id, 'variant_id' => $hidden->id, 'input_order' => 1],
            ],
        ])->assertStatus(422);

        $this->assertSame(0, Order::count());
    }

    public function test_order_falls_back_to_default_variant_without_variant_id(): void
    {
        $product = $this->makeProduct(25.50);

        $this->postJson('/api/checkouts', [
            'customer' => $this->customerPayload(),
            'orderProducts' => [
                ['id' => $product->id, 'input_order' => 1],
            ],
        ])->assertOk();

        $this->assertDatabaseHas('order_products', [
            'product_id'         => $product->id,
            'product_variant_id' => $this->variant($product)->id,
            'price'              => 25.50,
        ]);
    }
}
