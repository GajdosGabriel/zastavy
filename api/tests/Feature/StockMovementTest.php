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

    private function receiptPayload(array $variants): array
    {
        return [
            'uuid' => (string) Str::uuid(), 'received_at' => now()->toDateString(),
            'supplier' => 'Dodávateľ s.r.o.', 'supplier_ico' => '12345678',
            'warehouse' => 'Hlavný sklad', 'received_by' => 'Ján Skladník',
            'document_number' => 'DL-001', 'note' => 'Prevzaté bez poškodenia',
            'items' => array_map(fn ($variant) => [
                'product_variant_id' => $variant->id, 'quantity' => 3,
                'price' => 10, 'discount' => 10, 'vat' => 23, 'note' => 'Kontrola OK',
            ], $variants),
        ];
    }

    public function test_multi_item_receipt_is_saved_with_totals_and_snapshots(): void
    {
        $this->actingAsSuperAdmin();
        $first = $this->makeVariant(10);
        $second = $this->makeVariant(null);
        $response = $this->postJson(route('stocks.receipts.store'), $this->receiptPayload([$first, $second]))
            ->assertCreated()->assertJsonCount(2, 'data.items')
            ->assertJsonPath('data.net', 54)->assertJsonPath('data.tax', 12.42)
            ->assertJsonPath('data.total', 66.42);
        $this->assertStringStartsWith('PR-' . now()->format('Y') . '-', $response->json('data.number'));
        $this->assertSame(13, (int) $first->fresh()->quantity);
        $this->assertNull($second->fresh()->quantity);
        $this->assertEquals(9, Stock::where('stock_receipt_id', $response->json('data.id'))->first()->price);
        $originalCode = $first->code;
        $first->update(['code' => 'NEW-CODE']);
        $this->getJson(route('stocks.receipts.show', $response->json('data.id')))
            ->assertOk()->assertJsonPath('data.items.0.code', $originalCode);
    }

    public function test_invalid_receipt_item_does_not_save_anything(): void
    {
        $this->actingAsSuperAdmin();
        $first = $this->makeVariant(10);
        $second = $this->makeVariant(20);
        $payload = $this->receiptPayload([$first, $second]);
        $payload['items'][1]['quantity'] = 0;
        $this->postJson(route('stocks.receipts.store'), $payload)->assertUnprocessable()
            ->assertJsonValidationErrors('items.1.quantity');
        $this->assertDatabaseCount('stock_receipts', 0);
        $this->assertDatabaseCount('stocks', 0);
        $this->assertSame(10, (int) $first->fresh()->quantity);
        $this->assertSame(20, (int) $second->fresh()->quantity);
    }

    public function test_receipt_transaction_rolls_back_if_second_item_fails(): void
    {
        $this->actingAsSuperAdmin();
        $first = $this->makeVariant(10);
        $second = $this->makeVariant(20);
        $event = 'eloquent.creating: ' . Stock::class;
        \Illuminate\Support\Facades\Event::listen($event, function ($stock) use ($second) {
            if ($stock->product_variant_id === $second->id) {
                throw new \RuntimeException('Simulated storage failure');
            }
        });
        try {
            $this->postJson(route('stocks.receipts.store'), $this->receiptPayload([$first, $second]))->assertStatus(500);
            $this->assertDatabaseCount('stock_receipts', 0);
            $this->assertDatabaseCount('stocks', 0);
            $this->assertSame(10, (int) $first->fresh()->quantity);
            $this->assertSame(20, (int) $second->fresh()->quantity);
        } finally {
            \Illuminate\Support\Facades\Event::forget($event);
            Stock::observe(\App\Observers\StockObserver::class);
        }
    }

    public function test_retry_does_not_receive_the_same_goods_twice(): void
    {
        $this->actingAsSuperAdmin();
        $variant = $this->makeVariant(10);
        $payload = $this->receiptPayload([$variant]);
        $first = $this->postJson(route('stocks.receipts.store'), $payload)->assertCreated();
        $this->postJson(route('stocks.receipts.store'), $payload)->assertCreated()
            ->assertJsonPath('data.id', $first->json('data.id'));
        $this->assertDatabaseCount('stock_receipts', 1);
        $this->assertDatabaseCount('stocks', 1);
        $this->assertSame(13, (int) $variant->fresh()->quantity);
    }

    public function test_receipt_rejects_duplicates_deleted_variants_and_missing_prices(): void
    {
        $this->actingAsSuperAdmin();
        $variant = $this->makeVariant(10);
        $this->postJson(route('stocks.receipts.store'), $this->receiptPayload([$variant, $variant]))
            ->assertUnprocessable()->assertJsonValidationErrors('items.1.product_variant_id');
        $payload = $this->receiptPayload([$variant]);
        $payload['items'][0]['price'] = null;
        $payload['items'][0]['vat'] = 101;
        $payload['items'][0]['discount'] = -1;
        $this->postJson(route('stocks.receipts.store'), $payload)->assertUnprocessable()
            ->assertJsonValidationErrors(['items.0.price', 'items.0.vat', 'items.0.discount']);
        $variant->delete();
        $this->postJson(route('stocks.receipts.store'), $this->receiptPayload([$variant]))
            ->assertUnprocessable()->assertJsonValidationErrors('items.0.product_variant_id');
        $this->assertDatabaseCount('stock_receipts', 0);
    }

    public function test_whole_receipt_cancellation_is_reversible_once_and_preserves_document(): void
    {
        $this->actingAsSuperAdmin();
        $first = $this->makeVariant(10);
        $second = $this->makeVariant(20);
        $id = $this->postJson(route('stocks.receipts.store'), $this->receiptPayload([$first, $second]))->json('data.id');
        $stock = Stock::where('stock_receipt_id', $id)->first();
        $this->deleteJson(route('stocks.destroy', $stock))->assertUnprocessable();
        $this->putJson(route('stocks.update', $stock), ['quantity' => 8])->assertUnprocessable();
        $this->postJson(route('stocks.receipts.cancel', $id), ['reason' => ''])->assertUnprocessable();
        $this->postJson(route('stocks.receipts.cancel', $id), ['reason' => 'Omyl'])->assertOk()
            ->assertJsonCount(2, 'data.items')->assertJsonPath('data.cancellation_reason', 'Omyl');
        $this->postJson(route('stocks.receipts.cancel', $id), ['reason' => 'Opakovanie'])->assertOk()
            ->assertJsonPath('data.cancellation_reason', 'Omyl');
        $this->assertSame(10, (int) $first->fresh()->quantity);
        $this->assertSame(20, (int) $second->fresh()->quantity);
        $this->assertSame(0, Stock::count());
        $this->getJson(route('stocks.receipts.show', $id))->assertOk()->assertJsonCount(2, 'data.items');
        $this->getJson(route('stocks.receipts.index', ['status' => 'cancelled', 'search' => 'DL-001']))
            ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $id);
        $this->getJson(route('stocks.receipts.index', ['status' => 'active']))->assertOk()->assertJsonCount(0, 'data');
    }

    public function test_receipts_are_not_accessible_to_ordinary_users(): void
    {
        Sanctum::actingAs(User::factory()->create());
        $variant = $this->makeVariant(10);
        $this->postJson(route('stocks.receipts.store'), $this->receiptPayload([$variant]))->assertForbidden();
        $this->assertDatabaseCount('stock_receipts', 0);
    }

    public function test_discount_keeps_unit_cost_precision_and_rounds_document_lines(): void
    {
        $this->actingAsSuperAdmin();
        $variant = $this->makeVariant(0);
        $payload = $this->receiptPayload([$variant]);
        $payload['items'][0] = array_merge($payload['items'][0], ['quantity' => 100, 'price' => 0.01, 'discount' => 50, 'vat' => 0]);
        $id = $this->postJson(route('stocks.receipts.store'), $payload)->assertCreated()
            ->assertJsonPath('data.net', 0.5)->assertJsonPath('data.total', 0.5)->json('data.id');
        $this->assertEquals(0.005, (float) Stock::where('stock_receipt_id', $id)->first()->price);
        $this->getJson(route('stocks.summary'))->assertOk()->assertJsonPath('data.0.stock_value', 0.5);
    }
}
