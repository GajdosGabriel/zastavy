<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Order;
use App\Models\Product;
use App\Models\Stock;
use App\Models\User;
use App\Services\ShippingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class StockReliabilityTest extends TestCase
{
    use RefreshDatabase;

    private function fixture(?int $stock = 10, int $quantity = 4, bool $custom = false): array
    {
        Notification::fake();
        $user = User::factory()->create();
        $user->assignRole('super-admin');
        Sanctum::actingAs($user);
        $product = Product::create(['name' => 'Stock test', 'vat' => 20, 'code' => (string) Str::uuid(), 'made_to_order' => $custom]);
        $variant = $product->variants()->create(['code' => (string) Str::uuid(), 'quantity' => $stock, 'price' => 10]);
        $customer = Customer::create(['company' => 'Test', 'postcode' => '81101', 'city' => 'Bratislava', 'street' => 'Test 1']);
        $order = Order::create(['user_id' => $user->id, 'customer_id' => $customer->id]);
        $item = $order->orderProducts()->create(['product_id' => $product->id, 'product_variant_id' => $variant->id, 'quantity' => $quantity, 'price' => 10]);

        return [$order, $item, $variant];
    }

    public function test_insufficient_stock_rolls_back_entire_shipment(): void
    {
        [$order, $item, $variant] = $this->fixture(3);
        $this->postJson('/api/orders/'.$order->id.'/shippings')->assertUnprocessable();
        $this->assertSame(3, (int) $variant->fresh()->quantity);
        $this->assertDatabaseCount('shippings', 0);
        $this->assertDatabaseCount('stocks', 0);
    }

    public function test_multiple_lines_share_available_stock(): void
    {
        [$order, $item, $variant] = $this->fixture(6);
        $copy = $item->replicate();
        $copy->save();
        $this->postJson('/api/orders/'.$order->id.'/shippings')->assertUnprocessable();
        $this->assertSame(6, (int) $variant->fresh()->quantity);
        $this->assertDatabaseCount('stocks', 0);
    }

    public function test_custom_and_untracked_goods_can_ship_without_stock(): void
    {
        foreach ([[0, true], [null, false]] as [$stock, $custom]) {
            [$order, $item, $variant] = $this->fixture($stock, 4, $custom);
            $this->postJson('/api/orders/'.$order->id.'/shippings', ['notify_customer' => false])->assertSuccessful();
            $this->assertEquals($stock === null ? null : -4, $variant->fresh()->quantity);
        }
    }

    public function test_partial_shipping_retry_returns_original_and_rejects_changed_payload(): void
    {
        [$order, $item, $variant] = $this->fixture();
        $payload = ['idempotency_key' => (string) Str::uuid(), 'notify_customer' => false,
            'items' => [['order_product_id' => $item->id, 'quantity' => 2]]];
        $first = $this->postJson('/api/orders/'.$order->id.'/shippings', $payload)->assertSuccessful();
        $retry = $this->postJson('/api/orders/'.$order->id.'/shippings', $payload)->assertSuccessful();
        $this->assertSame($first->json('data.id'), $retry->json('data.id'));
        $this->assertSame(8, (int) $variant->fresh()->quantity);
        $this->assertDatabaseCount('shippings', 1);
        $payload['items'][0]['quantity'] = 1;
        $this->postJson('/api/orders/'.$order->id.'/shippings', $payload)->assertStatus(409);
    }

    public function test_service_ignores_stale_loaded_relations(): void
    {
        [$order, $item, $variant] = $this->fixture();
        $order->load('orderProducts.stocks');
        (new ShippingService)->create($order);
        $this->assertNull((new ShippingService)->create($order));
        $this->assertSame(6, (int) $variant->fresh()->quantity);
    }

    public function test_return_restock_and_damaged_disposition_and_retry(): void
    {
        foreach (['other' => 10, 'damaged' => 6] as $reason => $expected) {
            [$order, $item, $variant] = $this->fixture();
            (new ShippingService)->create($order);
            $return = $order->orderReturns()->create(['reason' => $reason, 'status' => 'pending', 'created_by' => $order->user_id]);
            $return->items()->create(['order_product_id' => $item->id, 'quantity' => 4]);
            $url = '/api/orders/'.$order->id.'/returns/'.$return->id.'/process';
            $this->postJson($url)->assertSuccessful();
            $this->postJson($url)->assertSuccessful();
            $this->assertSame($expected, (int) $variant->fresh()->quantity);
            $this->assertSame(0, $item->fresh()->stockSum);
            $this->assertSame(1, Stock::where('order_return_id', $return->id)->count());
        }
    }

    public function test_overlapping_pending_returns_revalidate_when_processed(): void
    {
        [$order, $item, $variant] = $this->fixture();
        (new ShippingService)->create($order);
        $returns = [];
        for ($i = 0; $i < 2; $i++) {
            $return = $order->orderReturns()->create(['reason' => 'other', 'status' => 'pending', 'created_by' => $order->user_id]);
            $return->items()->create(['order_product_id' => $item->id, 'quantity' => 3]);
            $returns[] = $return;
        }
        $this->postJson('/api/orders/'.$order->id.'/returns/'.$returns[0]->id.'/process')->assertSuccessful();
        $this->postJson('/api/orders/'.$order->id.'/returns/'.$returns[1]->id.'/process')->assertUnprocessable();
        $this->assertSame(9, (int) $variant->fresh()->quantity);
    }

    public function test_shipped_line_cannot_be_reduced_or_deleted_or_cancelled_beyond_quantity(): void
    {
        [$order, $item] = $this->fixture();
        (new ShippingService)->create($order);
        $url = '/api/orders/'.$order->id.'/orderProducts/'.$item->id;
        $this->putJson($url, ['quantity' => 2])->assertUnprocessable();
        $this->putJson($url, ['storno' => 1])->assertUnprocessable();
        $this->deleteJson($url)->assertForbidden();
        $stock = $item->stocks()->first();
        $this->putJson(route('stocks.update', $stock), ['quantity' => 1])->assertUnprocessable();
        $this->deleteJson(route('stocks.destroy', $stock))->assertUnprocessable();
    }

    public function test_receipt_update_delete_restore_and_untracked_are_consistent(): void
    {
        [$order, $item, $variant] = $this->fixture();
        $stock = Stock::create(['product_variant_id' => $variant->id, 'quantity' => 2]);
        $stock->update(['quantity' => 5]);
        $this->assertSame(15, (int) $variant->fresh()->quantity);
        $stock->delete();
        $this->assertSame(10, (int) $variant->fresh()->quantity);
        $stock->restore();
        $this->assertSame(15, (int) $variant->fresh()->quantity);
    }

    public function test_return_summary_uses_physical_delta(): void
    {
        [$order, $item, $variant] = $this->fixture();
        (new ShippingService)->create($order);
        $return = $order->orderReturns()->create(['reason' => 'other', 'status' => 'pending', 'created_by' => $order->user_id]);
        $return->items()->create(['order_product_id' => $item->id, 'quantity' => 2]);
        $url = '/api/orders/'.$order->id.'/returns/'.$return->id.'/process';
        $this->postJson($url, ['restock' => true])->assertSuccessful();
        $this->postJson($url, ['restock' => false])->assertStatus(409);
        $row = $this->getJson(route('stocks.summary'))->assertOk()->json('data.0');
        $this->assertSame(2, $row['total_in']);
        $this->assertSame(0, $row['total_writeoff']);
        $this->assertSame(8, (int) $row['tracked_quantity']);
        $movement = Stock::where('order_return_id', $return->id)->first();
        $this->getJson(route('stocks.show', $movement))->assertOk()->assertJsonPath('type', 'return');
    }

    public function test_duplicate_and_foreign_shipping_items_are_rejected(): void
    {
        [$order, $item] = $this->fixture();
        [$other, $foreign] = $this->fixture();
        $url = '/api/orders/'.$order->id.'/shippings';
        $line = ['order_product_id' => $item->id, 'quantity' => 1];
        $this->postJson($url, ['items' => [$line, $line]])->assertUnprocessable();
        $this->postJson($url, ['items' => [['order_product_id' => $foreign->id, 'quantity' => 1]]])->assertUnprocessable();
        $this->assertDatabaseCount('stocks', 0);
    }

    public function test_cancelled_order_cannot_ship(): void
    {
        [$order, $item, $variant] = $this->fixture();
        $this->putJson('/api/orders/'.$order->id, ['makeStorned' => true])->assertOk();
        $this->postJson('/api/orders/'.$order->id.'/shippings')->assertUnprocessable();
        $this->assertSame(10, (int) $variant->fresh()->quantity);
    }

    public function test_checkout_allows_order_above_stock_without_reservation(): void
    {
        [$order, $item, $variant] = $this->fixture(0);
        $variant->update(['published' => true, 'is_default' => true, 'min_order' => 1]);
        $variant->product->update(['published' => true]);
        $payload = ['customer' => ['company' => 'Backorder', 'name' => 'Test', 'email' => 'test@example.test',
            'phone' => '0900123456', 'street' => 'Test 1', 'postcode' => '81101', 'city' => 'Bratislava', 'ico' => '12345678'],
            'orderProducts' => [['id' => $variant->product_id, 'variant_id' => $variant->id, 'input_order' => 20]]];
        $this->postCheckout($payload)->assertOk();
        $this->assertSame(0, (int) $variant->fresh()->quantity);
        $this->assertSame(2, Order::count());
    }
}
