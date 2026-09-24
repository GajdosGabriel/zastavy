<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Order;
use App\Models\Product;
use App\Models\Stock;
use App\Models\User;
use App\Services\ShippingService;
use Illuminate\Foundation\Testing\RefreshDatabaseState;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\Process\Process;
use Tests\TestCase;

class StockConcurrencyTest extends TestCase
{
    use \Tests\Support\RunsConcurrentProcesses;
    protected function setUp(): void
    {
        parent::setUp();
        if (DB::getDriverName() !== 'mysql') {
            $this->markTestSkipped('Requires MySQL and independent processes.');
        }
        if (! str_contains(DB::connection()->getDatabaseName(), '_test')) {
            throw new \RuntimeException('Dedicated test database required.');
        }
        // Žiadna vonkajšia testovacia transakcia: procesy musia vidieť commit.
        $this->artisan('migrate:fresh', ['--force' => true])->assertExitCode(0);
        RefreshDatabaseState::$migrated = false;
    }

    private function fixture(int $stock = 4): array
    {
        $user = User::factory()->create();
        $user->assignRole('super-admin');
        $customer = Customer::create(['company' => 'Concurrency', 'postcode' => '81101', 'city' => 'Bratislava', 'street' => 'Test 1']);
        $product = Product::create(['name' => 'Concurrency', 'code' => (string) Str::uuid(), 'vat' => 20]);
        $variant = $product->variants()->create(['code' => (string) Str::uuid(), 'quantity' => $stock, 'price' => 10]);
        $order = Order::create(['customer_id' => $customer->id, 'user_id' => $user->id]);
        $item = $order->orderProducts()->create(['product_id' => $product->id, 'product_variant_id' => $variant->id, 'quantity' => 4, 'price' => 10]);

        return [$order, $item, $variant];
    }

    public function test_concurrent_receipts_do_not_lose_updates(): void
    {
        [$order, $item, $variant] = $this->fixture();
        $job = ['mode' => 'receipt', 'variant' => $variant->id];
        $results = $this->race($job, $job);
        $this->assertSame([200, 200], array_column($results, 'status'), json_encode($results));
        $this->assertSame(54, (int) $variant->fresh()->quantity);
        $this->assertSame(50, Stock::count());
    }

    public function test_same_partial_shipment_key_is_applied_once(): void
    {
        [$order, $item, $variant] = $this->fixture();
        $job = ['mode' => 'ship', 'order' => $order->id, 'key' => (string) Str::uuid(),
            'items' => [['order_product_id' => $item->id, 'quantity' => 2]]];
        $results = $this->race($job, $job);
        $this->assertSame($results[0]['id'], $results[1]['id']);
        $this->assertSame(2, (int) $variant->fresh()->quantity);
        $this->assertSame(1, $order->shippings()->count());
    }

    public function test_two_orders_cannot_ship_the_same_stock(): void
    {
        [$order, $item, $variant] = $this->fixture();
        $other = Order::create(['customer_id' => $order->customer_id, 'user_id' => $order->user_id]);
        $copy = $item->replicate();
        $copy->order_id = $other->id;
        $copy->save();
        $results = $this->race(['mode' => 'ship', 'order' => $order->id], ['mode' => 'ship', 'order' => $other->id]);
        $this->assertEqualsCanonicalizing([200, 422], array_column($results, 'status'));
        $this->assertSame(0, (int) $variant->fresh()->quantity);
        $this->assertSame(4, (int) Stock::sum('quantity'));
    }

    public function test_shipping_and_cancellation_preserve_item_quantities(): void
    {
        [$order, $item, $variant] = $this->fixture();
        $results = $this->race(['mode' => 'ship', 'order' => $order->id], ['mode' => 'api', 'user' => $order->user_id,
            'method' => 'PUT', 'url' => '/api/orders/'.$order->id, 'payload' => ['makeStorned' => true]]);
        $this->assertContains($results[1]['status'], [200, 403]);
        $item->refresh();
        $this->assertSame(4, (int) $item->storno + $item->stockSum);
        $this->assertSame(4 - $item->stockSum, (int) $variant->fresh()->quantity);
    }

    public function test_two_processes_cannot_process_return_twice(): void
    {
        [$order, $item, $variant] = $this->fixture();
        (new ShippingService)->create($order);
        $return = $order->orderReturns()->create(['reason' => 'other', 'status' => 'pending', 'created_by' => $order->user_id]);
        $return->items()->create(['order_product_id' => $item->id, 'quantity' => 4]);
        $job = ['mode' => 'api', 'user' => $order->user_id, 'url' => '/api/orders/'.$order->id.'/returns/'.$return->id.'/process'];
        $results = $this->race($job, $job);
        $this->assertSame([200, 200], array_column($results, 'status'));
        $this->assertSame(4, (int) $variant->fresh()->quantity);
        $this->assertSame(1, Stock::where('order_return_id', $return->id)->count());
    }
}
