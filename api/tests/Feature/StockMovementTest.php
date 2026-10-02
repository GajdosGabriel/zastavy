<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Models\Customer;
use App\Models\Order;
use App\Models\OrderProduct;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Shipping;
use App\Models\Stock;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class StockMovementTest extends TestCase
{
    use RefreshDatabase;

    private int $sequence = 0;

    private function makeVariant(?int $quantity = 10): ProductVariant
    {
        $suffix = ++$this->sequence;

        $product = Product::create([
            'name'       => 'Vlajka SR 100x150 #' . $suffix,
            'code'       => 'VSR-' . $suffix,
            'vat'        => 20,
            'published'  => 1,
            'unit_value' => 'ks',
        ]);

        return $product->variants()->create([
            'code'       => 'VSR-' . $suffix . '-100X150',
            'name'       => '100 × 150 cm',
            'price'      => 25.50,
            'quantity'   => $quantity,
            'min_order'  => 1,
            'is_default' => true,
            'published'  => 1,
        ]);
    }

    private function actingAsSuperAdmin(): User
    {
        $user = User::factory()->create();

        $role = Role::findOrCreate('super-admin', 'web');
        $user->assignRole($role);

        Sanctum::actingAs($user);

        return $user;
    }

    /**
     * Príjem musí zdvihnúť aj quantity na variante — to je číslo, ktorým sa
     * riadi dostupnosť v e-shope.
     */
    public function test_receipt_increases_variant_quantity(): void
    {
        $variant = $this->makeVariant(10);

        Stock::create([
            'product_id'         => $variant->product_id,
            'product_variant_id' => $variant->id,
            'quantity'           => 5,
        ]);

        $this->assertSame(15, (int) $variant->fresh()->quantity);
    }

    public function test_writeoff_decreases_variant_quantity(): void
    {
        $variant = $this->makeVariant(10);

        Stock::create([
            'product_id'         => $variant->product_id,
            'product_variant_id' => $variant->id,
            'quantity'           => -3,
            'note'               => 'Poškodené pri preprave',
        ]);

        $this->assertSame(7, (int) $variant->fresh()->quantity);
    }

    public function test_deleting_a_movement_reverses_it(): void
    {
        $variant = $this->makeVariant(10);

        $stock = Stock::create([
            'product_id'         => $variant->product_id,
            'product_variant_id' => $variant->id,
            'quantity'           => 5,
        ]);

        $this->assertSame(15, (int) $variant->fresh()->quantity);

        $stock->delete();

        $this->assertSame(10, (int) $variant->fresh()->quantity);
    }

    /**
     * quantity === null znamená "sklad sa nesleduje" — pohyb ho nesmie zapnúť.
     */
    public function test_untracked_variant_stays_untracked(): void
    {
        $variant = $this->makeVariant(null);

        Stock::create([
            'product_id'         => $variant->product_id,
            'product_variant_id' => $variant->id,
            'quantity'           => 5,
        ]);

        $this->assertNull($variant->fresh()->quantity);
    }

    /**
     * Expedícia sa eviduje cez položku objednávky a stav musí znižovať.
     */
    public function test_shipment_decreases_variant_quantity(): void
    {
        $variant = $this->makeVariant(10);

        $customer = Customer::create([
            'name'     => 'Kontaktná osoba',
            'company'  => 'Firma s.r.o.',
            'email'    => 'odberatel@example.com',
            'phone'    => '+421911222333',
            'street'   => 'Hlavná 1',
            'postcode' => '01001',
            'city'     => 'Žilina',
        ]);

        $order = Order::create([
            'uuid'        => (string) Str::uuid(),
            'status'      => OrderStatus::Processing,
            'customer_id' => $customer->id,
        ]);

        $orderProduct = OrderProduct::create([
            'order_id'           => $order->id,
            'product_id'         => $variant->product_id,
            'product_variant_id' => $variant->id,
            'quantity'           => 4,
            'price'              => 25.50,
        ]);

        $shipping = Shipping::create(['order_id' => $order->id]);

        Stock::create([
            'order_id'         => $order->id,
            'order_product_id' => $orderProduct->id,
            'shipping_id'      => $shipping->id,
            'quantity'         => 4,
        ]);

        $this->assertSame(6, (int) $variant->fresh()->quantity);
    }

    public function test_summary_separates_receipts_writeoffs_and_shipments(): void
    {
        $this->actingAsSuperAdmin();

        $variant = $this->makeVariant(10);

        Stock::create([
            'product_id'         => $variant->product_id,
            'product_variant_id' => $variant->id,
            'quantity'           => 20,
            'price'              => 3.50,
        ]);

        Stock::create([
            'product_id'         => $variant->product_id,
            'product_variant_id' => $variant->id,
            'quantity'           => -2,
        ]);

        $response = $this->getJson(route('stocks.summary'))->assertOk();

        $row = $response->json('data.0');

        $this->assertSame(20, $row['total_in']);
        $this->assertSame(2, $row['total_writeoff']);
        $this->assertSame(0, $row['total_out']);
        $this->assertSame(18, $row['balance']);
        // Observer priebežne dorovnal aj stĺpec, ktorým sa riadi e-shop: 10 + 20 − 2.
        $this->assertSame(28, (int) $row['tracked_quantity']);
        $this->assertEqualsWithDelta(3.5, (float) $row['avg_price'], 0.001);
        $this->assertEqualsWithDelta(63.0, (float) $row['stock_value'], 0.001);
    }

    public function test_store_rejects_zero_quantity(): void
    {
        $this->actingAsSuperAdmin();

        $variant = $this->makeVariant(10);

        $this->postJson(route('stocks.store'), [
            'product_variant_id' => $variant->id,
            'quantity'           => 0,
        ])->assertStatus(422)->assertJsonValidationErrors('quantity');
    }

    public function test_store_saves_receipt_document_fields(): void
    {
        $this->actingAsSuperAdmin();

        $variant = $this->makeVariant(10);

        $this->postJson(route('stocks.store'), [
            'product_variant_id' => $variant->id,
            'quantity'           => 5,
            'price'              => 3.45,
            'supplier'           => 'Vlajky s.r.o.',
            'document_number'    => 'DL-2026/0153',
            'received_at'        => now()->subDay()->toDateString(),
        ])
            ->assertCreated()
            ->assertJsonPath('supplier', 'Vlajky s.r.o.')
            ->assertJsonPath('document_number', 'DL-2026/0153')
            ->assertJsonPath('received_at', now()->subDay()->toDateString());

        $this->assertSame(15, (int) $variant->fresh()->quantity);
    }

    /**
     * Bez dátumu z formulára platí dnešok — príjemka nesmie ostať bez dátumu.
     */
    public function test_store_defaults_received_at_to_today(): void
    {
        $this->actingAsSuperAdmin();

        $variant = $this->makeVariant(10);

        $this->postJson(route('stocks.store'), [
            'product_variant_id' => $variant->id,
            'quantity'           => 1,
        ])->assertCreated()->assertJsonPath('received_at', now()->toDateString());
    }

    public function test_store_rejects_invalid_receipt_values(): void
    {
        $this->actingAsSuperAdmin();

        $variant = $this->makeVariant(10);

        $this->postJson(route('stocks.store'), [
            'product_variant_id' => $variant->id,
            'quantity'           => 1.5,
            'price'              => 1.234,
            'document_number'    => str_repeat('x', 65),
            'received_at'        => now()->addDay()->toDateString(),
        ])->assertStatus(422)->assertJsonValidationErrors(['quantity', 'price', 'document_number', 'received_at']);

        $this->postJson(route('stocks.store'), [
            'product_variant_id' => $variant->id,
            'quantity'           => 100001,
            'price'              => -1,
        ])->assertStatus(422)->assertJsonValidationErrors(['quantity', 'price']);

        $this->assertSame(10, (int) $variant->fresh()->quantity);
    }

    public function test_store_rejects_deleted_variant(): void
    {
        $this->actingAsSuperAdmin();

        $variant = $this->makeVariant(10);
        $variant->delete();

        $this->postJson(route('stocks.store'), [
            'product_variant_id' => $variant->id,
            'quantity'           => 1,
        ])->assertStatus(422)->assertJsonValidationErrors('product_variant_id');
    }

    /**
     * Odpis musí mať dôvod a nesmie niesť nákupnú cenu — tá by skreslila hodnotu skladu.
     */
    public function test_writeoff_requires_reason_and_has_no_price(): void
    {
        $this->actingAsSuperAdmin();

        $variant = $this->makeVariant(10);

        $this->postJson(route('stocks.store'), [
            'product_variant_id' => $variant->id,
            'quantity'           => -2,
            'price'              => 3.50,
        ])->assertStatus(422)->assertJsonValidationErrors(['note', 'price']);

        $this->postJson(route('stocks.store'), [
            'product_variant_id' => $variant->id,
            'quantity'           => -2,
            'note'               => 'Poškodené pri preprave',
        ])->assertCreated();

        $this->assertSame(8, (int) $variant->fresh()->quantity);
    }

    /**
     * Výber vo formulári príjmu nesmie byť stránkovaný — inak sa dá naskladniť
     * len prvá stránka produktov.
     */
    public function test_variants_endpoint_lists_every_variant(): void
    {
        $this->actingAsSuperAdmin();

        $this->makeVariant(10);
        $this->makeVariant(null);

        $response = $this->getJson(route('stocks.variants'))->assertOk();

        $this->assertCount(2, $response->json('data'));
        $this->assertArrayHasKey('tracked_quantity', $response->json('data.0'));
        $this->assertArrayHasKey('balance', $response->json('data.0'));
    }
}
