<?php

namespace Tests\Feature;

use App\Models\Coupon;
use App\Models\Order;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
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

    private function makeProduct(float $price = 25.50): Product
    {
        return Product::create([
            'name' => 'Vlajka SR 100x150',
            'code' => 'VSR-100',
            'price' => $price,
            'sale_price' => 0,
            'vat' => 20,
            'published' => 1,
            'min_order' => 1,
        ]);
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
        $product->update(['sale_price' => 19.99]);

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
}
