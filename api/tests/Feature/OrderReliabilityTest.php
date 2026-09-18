<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\Product;
use App\Models\ShippingMethod;
use App\Models\User;
use App\Notifications\OrderCancelled;
use App\Notifications\OrderCreated;
use App\Notifications\OrderDeliveryAddressChanged;
use App\Notifications\OrderExpedition;
use App\Notifications\OrderReturnProcessed;
use App\Notifications\OrderUpdated;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class OrderReliabilityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Notification::fake();
    }

    private function payload(): array
    {
        $product = Product::create(['name' => 'Original flag', 'code' => 'FLAG-'.Str::random(6), 'published' => true, 'vat' => 20, 'unit_value' => 'ks']);
        $variant = $product->variants()->create(['code' => 'VAR-'.Str::random(6), 'name' => 'Original size', 'price' => 10, 'published' => true, 'is_default' => true, 'min_order' => 1]);
        $shipping = ShippingMethod::create(['name' => 'Original delivery', 'price' => 4, 'active' => true]);

        return [
            'idempotency_key' => (string) Str::uuid(), 'shipping_method_id' => $shipping->id,
            'customer' => ['company' => 'Original company', 'name' => 'Original contact', 'email' => 'buyer@example.test', 'phone' => '0900123456', 'street' => 'Original 1', 'city' => 'Nitra', 'postcode' => '94901', 'ico' => '12345678'],
            'orderProducts' => [['id' => $product->id, 'variant_id' => $variant->id, 'input_order' => 2]],
        ];
    }

    public function test_identical_retry_returns_same_order_and_sends_only_one_confirmation(): void
    {
        $payload = $this->payload();
        $first = $this->postJson('/api/checkouts', $payload)->assertOk()->json();
        $this->postJson('/api/checkouts', $payload)->assertOk()->assertExactJson($first);
        $this->assertDatabaseCount('orders', 1);
        $this->assertDatabaseCount('customers', 1);
        $this->assertDatabaseCount('order_products', 1);
        Notification::assertSentToTimes(Order::firstOrFail(), OrderCreated::class, 1);
    }

    public function test_changed_payload_with_same_key_is_rejected(): void
    {
        $payload = $this->payload();
        $this->postJson('/api/checkouts', $payload)->assertOk();
        $payload['orderProducts'][0]['input_order'] = 3;
        $this->postJson('/api/checkouts', $payload)->assertConflict();
        $this->assertDatabaseCount('orders', 1);
    }

    public function test_failed_checkout_does_not_consume_key_or_number(): void
    {
        $payload = $this->payload();
        $payload['coupon_code'] = 'INVALID';
        $this->postJson('/api/checkouts', $payload)->assertUnprocessable();
        $this->assertDatabaseCount('checkout_submissions', 0);
        $this->assertDatabaseCount('orders', 0);
        unset($payload['coupon_code']);
        $this->postJson('/api/checkouts', $payload)->assertOk()->assertJsonPath('serial_number', now()->format('Y-m').'-0001');
    }

    public function test_retry_survives_catalog_changes_and_soft_deleted_order(): void
    {
        $payload = $this->payload();
        $first = $this->postJson('/api/checkouts', $payload)->assertOk()->json();
        ShippingMethod::find($payload['shipping_method_id'])->delete();
        Product::find($payload['orderProducts'][0]['id'])->delete();
        Order::firstOrFail()->delete();
        $this->postJson('/api/checkouts', $payload)->assertOk()->assertExactJson($first);
        $this->assertEquals(1, Order::withTrashed()->count());
    }

    public function test_retry_key_cannot_be_used_by_another_actor(): void
    {
        $payload = $this->payload();
        $this->postJson('/api/checkouts', $payload)->assertOk();
        Sanctum::actingAs(User::factory()->create());
        $this->postJson('/api/checkouts', $payload)->assertConflict();
    }

    public function test_snapshot_survives_customer_and_catalog_edits_in_api_and_email(): void
    {
        $payload = $this->payload();
        $this->postJson('/api/checkouts', $payload)->assertOk();
        $order = Order::firstOrFail();
        $order->customer->update(['company' => 'Changed company', 'email' => 'wrong@example.test', 'street' => 'Changed 99']);
        $item = $order->orderProducts->first();
        $item->product->update(['name' => 'Changed flag', 'unit_value' => 'kg', 'vat' => 99]);
        $item->variant->update(['name' => 'Changed size', 'price' => 90]);
        $order->shippingMethod->update(['name' => 'Changed delivery']);
        $item->product->delete();
        $order->refresh();
        $this->getJson('/api/public-orders/'.$order->uuid)->assertOk()
            ->assertJsonPath('data.customer.company', 'Original company')
            ->assertJsonPath('data.customer.email', 'buyer@example.test')
            ->assertJsonPath('data.delivery.street', 'Original 1')
            ->assertJsonPath('data.order_products.0.name', 'Original flag')
            ->assertJsonPath('data.order_products.0.variant', 'Original size')
            ->assertJsonPath('data.order_products.0.price', fn ($price) => (float) $price === 10.0)
            ->assertJsonPath('data.shipping_method.name', 'Original delivery');
        $this->assertSame('buyer@example.test', $order->routeNotificationForMail());
        $order->notifyCustomer(new OrderExpedition($order, null));
        Notification::assertSentTo($order, OrderExpedition::class, fn ($notification, $channels, $recipient) => $recipient->routeNotificationForMail() === 'buyer@example.test');
        $html = view('emails.orderConfirmation', ['order' => $order])->render();
        $this->assertStringContainsString('Original company', $html);
        $this->assertStringContainsString('Original flag', $html);
        $this->assertStringNotContainsString('Changed company', $html);
        $this->assertEquals(20, $item->fresh()->product_details->vat);
    }

    public function test_missing_or_inactive_delivery_is_rejected(): void
    {
        $payload = $this->payload();
        $missing = $payload;
        unset($missing['shipping_method_id']);
        $this->postJson('/api/checkouts', $missing)->assertUnprocessable()->assertJsonValidationErrors('shipping_method_id');
        ShippingMethod::find($payload['shipping_method_id'])->update(['active' => false]);
        $this->postJson('/api/checkouts', $payload)->assertUnprocessable()->assertJsonValidationErrors('shipping_method_id');
        $this->assertDatabaseCount('orders', 0);
    }

    public function test_delivery_is_optional_when_no_method_is_active(): void
    {
        $payload = $this->payload();
        unset($payload['shipping_method_id']);
        ShippingMethod::query()->update(['active' => false]);
        $this->postJson('/api/checkouts', $payload)->assertSuccessful();
        $this->assertNull(\App\Models\Order::first()->shipping_method_id);
    }

    public function test_variant_must_belong_to_selected_product(): void
    {
        $payload = $this->payload();
        $other = $this->payload();
        $payload['orderProducts'][0]['variant_id'] = $other['orderProducts'][0]['variant_id'];
        $this->postJson('/api/checkouts', $payload)->assertUnprocessable()->assertJsonValidationErrors('orderProducts');
        $this->assertDatabaseCount('customers', 0);
    }

    public function test_submission_key_is_required(): void
    {
        $payload = $this->payload();
        unset($payload['idempotency_key']);
        $this->postJson('/api/checkouts', $payload)->assertUnprocessable()->assertJsonValidationErrors('idempotency_key');
    }

    public function test_number_sequence_never_reuses_deleted_number(): void
    {
        $payload = $this->payload();
        DB::table('order_sequences')->insert(['period' => now()->format('Y-m'), 'last_number' => 9]);
        $this->postJson('/api/checkouts', $payload)->assertOk()->assertJsonPath('serial_number', now()->format('Y-m').'-0010');
        Order::firstOrFail()->forceDelete();
        $payload['idempotency_key'] = (string) Str::uuid();
        $this->postJson('/api/checkouts', $payload)->assertOk()->assertJsonPath('serial_number', now()->format('Y-m').'-0011');
    }

    public function test_database_rejects_duplicate_serial_number(): void
    {
        $payload = $this->payload();
        $this->postJson('/api/checkouts', $payload)->assertOk();
        $order = Order::firstOrFail();
        $this->expectException(QueryException::class);
        Order::create(['customer_id' => $order->customer_id, 'serial_number' => $order->serial_number]);
    }

    public function test_legacy_freeze_is_explicit_and_does_not_replace_captured_data(): void
    {
        $this->postJson('/api/checkouts', $this->payload())->assertOk();
        $order = Order::firstOrFail();
        DB::table('orders')->where('id', $order->id)->update(['billing_snapshot' => null, 'snapshot_source' => null]);
        $this->artisan('orders:freeze-history')->assertSuccessful();
        $this->assertNull($order->fresh()->billing_snapshot);
        $this->artisan('orders:freeze-history --apply')->assertSuccessful();
        $this->assertSame('reconstructed', $order->fresh()->snapshot_source);
        $order->customer->update(['company' => 'Changed company']);
        $this->artisan('orders:freeze-history --apply')->assertSuccessful();
        $this->assertSame('Original company', $order->fresh()->billing->company);
    }

    public function test_same_upload_is_not_stored_twice(): void
    {
        config(['media.disk' => 'local']);
        Storage::fake('local');
        $payload = $this->payload();
        $payload['attachments'] = [UploadedFile::fake()->createWithContent('design.pdf', '%PDF original')];
        $this->postJson('/api/checkouts', $payload)->assertOk();
        $payload['attachments'] = [UploadedFile::fake()->createWithContent('design.pdf', '%PDF original')];
        $this->postJson('/api/checkouts', $payload)->assertOk();
        $this->assertDatabaseCount('attachments', 1);
        $payload['attachments'] = [UploadedFile::fake()->createWithContent('design.pdf', '%PDF changed')];
        $this->postJson('/api/checkouts', $payload)->assertConflict();
    }

    public function test_dashboard_uses_same_submission_protection(): void
    {
        $user = User::factory()->create();
        $user->assignRole('super-admin');
        Sanctum::actingAs($user);
        $payload = $this->payload();
        $first = $this->postJson('/api/orders', $payload)->assertSuccessful()->json('data.id');
        $this->postJson('/api/orders', $payload)->assertSuccessful()->assertJsonPath('data.id', $first);
        $this->assertDatabaseCount('orders', 1);
    }

    #[DataProvider('customerNotifications')]
    public function test_order_operations_notify_original_buyer(string $operation, string $notification): void
    {
        $payload = $this->payload();
        $this->postJson('/api/checkouts', $payload)->assertOk();
        $order = Order::firstOrFail();
        $order->customer->update(['email' => 'another-contact@example.test']);
        $staff = User::factory()->create();
        $staff->assignRole('super-admin');
        Sanctum::actingAs($staff);
        if ($operation === 'update') {
            $this->putJson('/api/orders/'.$order->id, ['note' => 'Updated note', 'notify_customer' => true])->assertOk();
        }
        if ($operation === 'cancel') {
            $this->putJson('/api/orders/'.$order->id, ['makeStorned' => true, 'notify_customer' => true])->assertOk();
        }
        if ($operation === 'ship') {
            $this->postJson('/api/orders/'.$order->id.'/shippings', ['notify_customer' => true])->assertSuccessful();
        }
        if ($operation === 'delivery') {
            $this->putJson('/api/public-orders/'.$order->uuid.'/delivery-address?token='.$order->delivery_token,
                ['delivery' => ['street' => 'Other 2', 'city' => 'Nitra', 'postcode' => '94901']])->assertOk();
        }
        if ($operation === 'return') {
            $return = $order->orderReturns()->create(['reason' => 'other', 'status' => 'pending', 'created_by' => $staff->id]);
            $this->postJson('/api/orders/'.$order->id.'/returns/'.$return->id.'/process', ['notify_customer' => true])->assertOk();
        }
        Notification::assertSentTo($order, $notification, fn ($mail, $channels, $recipient) => $recipient->routeNotificationForMail() === 'buyer@example.test');
        Notification::assertNotSentTo($order->customer, $notification);
    }

    public static function customerNotifications(): array
    {
        return [
            ['update', OrderUpdated::class], ['cancel', OrderCancelled::class],
            ['ship', OrderExpedition::class], ['delivery', OrderDeliveryAddressChanged::class],
            ['return', OrderReturnProcessed::class],
        ];
    }

    public function test_delivery_edits_compare_with_original_billing_address(): void
    {
        $this->postJson('/api/checkouts', $this->payload())->assertOk();
        $order = Order::firstOrFail();
        $order->customer->update(['street' => 'New headquarters 9']);
        $url = '/api/public-orders/'.$order->uuid.'/delivery-address?token='.$order->delivery_token;
        $this->getJson($url)->assertOk()->assertJsonPath('data.billing.street', 'Original 1');
        $this->putJson($url, ['delivery' => ['street' => 'New headquarters 9', 'city' => 'Nitra', 'postcode' => '94901']])
            ->assertOk()->assertJsonPath('data.delivery.street', 'New headquarters 9')->assertJsonPath('data.delivery.is_custom', true);
        $this->putJson($url, ['delivery' => null])->assertOk()->assertJsonPath('data.delivery.street', 'Original 1');
    }

    public function test_changing_shipping_recalculates_price_and_preserves_label(): void
    {
        $this->postJson('/api/checkouts', $this->payload())->assertOk();
        $order = Order::firstOrFail();
        $staff = User::factory()->create();
        $staff->assignRole('super-admin');
        Sanctum::actingAs($staff);
        $shipping = ShippingMethod::create(['name' => 'Express', 'price' => 9, 'active' => true]);
        $this->putJson('/api/orders/'.$order->id, ['shipping_method_id' => $shipping->id])->assertOk();
        $this->assertEquals(9, $order->fresh()->shipping_price);
        $this->assertSame('Express', $order->fresh()->shipping_method_name);
        $shipping->delete();
        $this->getJson('/api/public-orders/'.$order->uuid)->assertOk()->assertJsonPath('data.shipping_method.name', 'Express');
    }
}
