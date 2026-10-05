<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Order;
use App\Models\OrderProduct;
use App\Models\Product;
use App\Models\Stock;
use App\Models\SystemLog;
use App\Models\User;
use App\Services\Customers\CustomerDuplicateService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class BusinessActivityLogTest extends TestCase
{
    use RefreshDatabase;

    private User $staff;

    private Order $order;

    private OrderProduct $item;

    protected function setUp(): void
    {
        parent::setUp();
        Notification::fake();
        $this->staff = User::factory()->create();
        $this->staff->assignRole('super-admin');
        Sanctum::actingAs($this->staff);
        $customer = Customer::create(['company' => 'Buyer', 'email' => 'buyer@example.test', 'street' => 'Original 1', 'postcode' => '81101', 'city' => 'Bratislava']);
        $this->order = Order::create(['customer_id' => $customer->id, 'serial_number' => 'TEST-1']);
        $product = Product::create(['name' => 'Flag', 'code' => 'LOG-FLAG', 'vat' => 20, 'unit_value' => 'ks']);
        $variant = $product->variants()->create(['code' => 'LOG-VAR', 'price' => 10, 'quantity' => 20]);
        $this->item = $this->order->orderProducts()->create(['product_id' => $product->id, 'product_variant_id' => $variant->id, 'quantity' => 4, 'price' => 10, 'total' => 40]);
        SystemLog::query()->delete();
    }

    public function test_order_edits_have_before_after_actor_and_no_duplicate_for_noop(): void
    {
        $url = '/api/orders/'.$this->order->id;
        $this->putJson($url, ['note' => 'Call before delivery'])->assertOk();
        $log = SystemLog::where('event', 'order.updated')->sole();
        // MySQL JSON storage may reorder object keys; compare their values strictly.
        $noteChange = $log->context['changes']['note'];
        $this->assertCount(2, $noteChange);
        $this->assertArrayHasKey('before', $noteChange);
        $this->assertNull($noteChange['before']);
        $this->assertSame('Call before delivery', $noteChange['after']);
        $this->assertSame($this->staff->id, $log->user_id);
        $this->assertSame('TEST-1', $log->context['serial_number']);
        $this->putJson($url, ['note' => 'Call before delivery'])->assertOk();
        $this->assertSame(1, SystemLog::where('event', 'order.updated')->count());
        $this->putJson($url, ['makeStorned' => true])->assertOk();
        $this->assertSame(1, SystemLog::where('event', 'order.cancelled')->count());
    }

    public function test_price_and_delivery_changes_rollback_with_the_order(): void
    {
        DB::beginTransaction();
        try {
            $this->order->update(['shipping_price' => 5, 'delivery_street' => 'New 2']);
            $this->assertSame(1, SystemLog::where('event', 'order.price_changed')->count());
            $this->assertSame(1, SystemLog::where('event', 'order.delivery_changed')->count());
        } finally {
            DB::rollBack();
        }
        $this->assertDatabaseCount('system_logs', 0);
        $this->assertNull($this->order->fresh()->delivery_street);
    }

    public function test_item_changes_and_removal_are_logged_with_quantity_and_price(): void
    {
        $url = '/api/orders/'.$this->order->id.'/orderProducts/'.$this->item->id;
        $this->putJson($url, ['quantity' => 3, 'price' => 12])->assertNoContent();
        $log = SystemLog::where('event', 'order.item_updated')->sole();
        $this->assertEquals(4, $log->context['changes']['quantity']['before']);
        $this->assertEquals(3, $log->context['changes']['quantity']['after']);
        $this->assertEquals(10, $log->context['changes']['price']['before']);
        $this->assertEquals(12, $log->context['changes']['price']['after']);
        $this->deleteJson($url)->assertNoContent();
        $this->assertSame(1, SystemLog::where('event', 'order.item_deleted')->count());
    }

    public function test_shipping_and_return_retries_do_not_duplicate_business_events(): void
    {
        $url = '/api/orders/'.$this->order->id;
        $shipping = $this->postJson($url.'/shippings', ['notify_customer' => false,
            'items' => [['order_product_id' => $this->item->id, 'quantity' => 4]]])->assertSuccessful()->json('data.id');
        $this->putJson($url.'/shippings/'.$shipping, ['notify_customer' => false])->assertSuccessful();
        $this->putJson($url.'/shippings/'.$shipping, ['notify_customer' => false])->assertSuccessful();
        $this->assertSame(1, SystemLog::where('event', 'order.prepared')->count());
        $this->assertSame(1, SystemLog::where('event', 'order.dispatched')->count());
        $returnId = $this->postJson($url.'/returns', ['reason' => 'other',
            'items' => [['order_product_id' => $this->item->id, 'quantity' => 2]]])->assertSuccessful()->json('data.id');
        $created = SystemLog::where('event', 'order.return_created')->sole();
        $this->assertSame(2, $created->context['items'][0]['quantity']);
        $this->putJson($url.'/returns/'.$returnId, ['items' => [['order_product_id' => $this->item->id, 'quantity' => 1]]])->assertSuccessful();
        $this->assertSame(1, SystemLog::where('event', 'order.return_items_updated')->count());
        $this->postJson($url.'/returns/'.$returnId.'/process', ['notify_customer' => false])->assertSuccessful();
        $this->postJson($url.'/returns/'.$returnId.'/process', ['notify_customer' => false])->assertSuccessful();
        $log = SystemLog::where('event', 'order.return_processed')->sole();
        $this->assertSame(1, $log->context['items'][0]['quantity']);
        $this->assertSame(2, SystemLog::where('event', 'stock.created')->count());
    }

    public function test_failed_dispatch_has_no_dispatch_or_stock_event(): void
    {
        $this->item->variant->update(['quantity' => 0]);
        $url = '/api/orders/'.$this->order->id.'/shippings';
        $id = $this->postJson($url, ['notify_customer' => false,
            'items' => [['order_product_id' => $this->item->id, 'quantity' => 4]]])->assertSuccessful()->json('data.id');
        $this->putJson($url.'/'.$id)->assertUnprocessable();
        $this->assertSame(0, SystemLog::whereIn('event', ['order.dispatched', 'stock.created'])->count());
    }

    public function test_stock_edit_delete_restore_preserve_inventory_and_log_once(): void
    {
        $variant = $this->item->variant;
        $stock = Stock::create(['product_id' => $variant->product_id, 'product_variant_id' => $variant->id, 'quantity' => 3]);
        $stock->update(['quantity' => 5]);
        $stock->delete();
        $stock->restore();
        $this->assertEquals(25, $variant->fresh()->quantity);
        foreach (['created', 'updated', 'deleted', 'restored'] as $event) {
            $this->assertSame(1, SystemLog::where('event', 'stock.'.$event)->count());
        }
    }

    public function test_user_deactivation_excludes_credentials_and_roles_ignore_noop(): void
    {
        $user = User::factory()->create();
        $user->update(['active' => false, 'password' => bcrypt('private-password')]);
        $log = SystemLog::where('event', 'user.deactivated')->sole();
        $this->assertArrayNotHasKey('password', $log->context['changes']);
        $payload = ['firstName' => $user->firstName, 'lastName' => $user->lastName,
            'username' => $user->username, 'email' => $user->email, 'status' => 'active', 'roles' => ['manager']];
        $this->putJson(route('users.update', $user), $payload)->assertOk();
        $this->putJson(route('users.update', $user), $payload)->assertOk();
        $roleLog = SystemLog::where('event', 'user.roles_changed')->sole();
        $this->assertSame(['manager'], $roleLog->context['changes']['roles']['after']);
        $this->assertSame($this->staff->id, $roleLog->user_id);
    }

    public function test_customer_merge_logs_source_ids_and_moved_orders(): void
    {
        $keep = Customer::create(['company' => 'Keep', 'postcode' => '81101', 'city' => 'Bratislava']);
        $source = $this->order->customer;
        app(CustomerDuplicateService::class)->merge($keep, [$source->id]);
        $log = SystemLog::where('event', 'customer.merged')->sole();
        $this->assertSame([$source->id], $log->context['merged_ids']);
        $this->assertSame(1, $log->context['orders_moved']);
        $this->assertSame($keep->id, $this->order->fresh()->customer_id);
    }
}
