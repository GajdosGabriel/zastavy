<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\Product;
use App\Models\ShippingMethod;
use App\Models\User;
use App\Notifications\OrderExpedition;
use App\Notifications\OrderPreparing;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CustomOrderPreparationTest extends TestCase
{
    use RefreshDatabase;

    private function payload(): array
    {
        Notification::fake();
        $product = Product::create(['name' => 'Vlajka', 'vat' => 20, 'code' => Str::uuid(), 'published' => true, 'unit_value' => 'ks']);
        $variant = $product->variants()->create(['code' => Str::uuid(), 'price' => 10, 'quantity' => 10, 'is_default' => true, 'published' => true]);
        $shipping = ShippingMethod::create(['name' => 'Pošta', 'price' => 4, 'active' => true]);

        return ['idempotency_key' => (string) Str::uuid(), 'shipping_method_id' => $shipping->id,
            'customer' => ['company' => 'Obec', 'name' => 'Test', 'email' => 'buyer@example.test', 'phone' => '0900123456', 'street' => 'Test 1', 'city' => 'Nitra', 'postcode' => '94901'],
            'orderProducts' => [['id' => $product->id, 'variant_id' => $variant->id, 'input_order' => 2]]];
    }

    private function staff(): void
    {
        $staff = User::factory()->create();
        $staff->assignRole('super-admin');
        Sanctum::actingAs($staff);
    }

    public function test_custom_lines_and_adjustments_are_staff_only_and_totals_match(): void
    {
        $payload = $this->payload();
        $payload['orderProducts'][] = ['is_custom' => true, 'id' => null, 'name' => 'Grafická príprava', 'unit_value' => 'úkon', 'input_order' => 1, 'active_price' => 25, 'vat' => 23];
        $payload['price_adjustment'] = ['direction' => 'discount', 'type' => 'percent', 'value' => 10, 'label' => 'Dohodnutá zľava'];
        $this->postJson('/api/checkouts', $payload)->assertUnprocessable();
        $this->staff();
        $id = $this->postJson('/api/orders', $payload)->assertSuccessful()->assertJsonPath('data.grand_total', 44.5)->json('data.id');
        $order = Order::findOrFail($id);
        $custom = $order->orderProducts->firstWhere('is_custom', true);
        $this->assertNull($custom->product_id);
        $this->assertSame('Grafická príprava', $custom->product_details->name);
        $this->assertSame(23, $custom->product_details->vat);
        $this->getJson('/api/public-orders/'.$order->uuid)->assertOk()->assertJsonPath('data.grand_total', 44.5)->assertJsonPath('data.adjustment_amount', -4.5);
        $html = view('emails.orderConfirmation', ['order' => $order])->render();
        $this->assertStringContainsString('44,50', $html);
        $this->assertStringContainsString('Grafická príprava', $html);
        $this->putJson('/api/orders/'.$id, ['price_adjustment' => ['direction' => 'surcharge', 'type' => 'fixed', 'value' => 5]])->assertOk()->assertJsonPath('data.grand_total', 54);
        $this->putJson('/api/orders/'.$id, ['price_adjustment' => null])->assertOk()->assertJsonPath('data.grand_total', 49);
    }

    public function test_preparation_preview_dispatch_and_retry_have_separate_effects(): void
    {
        $payload = $this->payload();
        $this->staff();
        $payload['orderProducts'][] = ['is_custom' => true, 'name' => 'Karabínka', 'input_order' => 2, 'active_price' => 1, 'vat' => 10];
        $id = $this->postJson('/api/orders', $payload)->assertSuccessful()->json('data.id');
        $order = Order::findOrFail($id);
        $rows = $order->orderProducts->map(fn ($item) => ['order_product_id' => $item->id, 'quantity' => $item->quantity])->all();
        $request = ['idempotency_key' => (string) Str::uuid(), 'items' => $rows, 'notify_customer' => true];
        $url = '/api/orders/'.$id.'/shippings';
        $preview = $this->postJson($url.'/preview', $request)->assertOk()->json();
        $this->assertStringContainsString('pripravujeme v sklade', $preview['subject']);
        $this->assertDatabaseCount('shippings', 0);
        $shippingId = $this->postJson($url, $request)->assertSuccessful()->assertJsonPath('data.is_preparing', true)->json('data.id');
        $this->postJson($url, $request)->assertSuccessful()->assertJsonPath('data.id', $shippingId);
        $this->assertDatabaseCount('stocks', 0);
        $this->assertSame(10, (int) $order->orderProducts->first()->variant->fresh()->quantity);
        Notification::assertSentToTimes($order, OrderPreparing::class, 1);
        Notification::assertNotSentTo($order, OrderExpedition::class);
        $this->assertFalse($order->fresh()->isFinished());
        $request['idempotency_key'] = (string) Str::uuid();
        $this->postJson($url, $request)->assertConflict();
        $this->putJson($url.'/'.$shippingId, ['notify_customer' => true])->assertOk()->assertJsonPath('data.is_preparing', false);
        $this->putJson($url.'/'.$shippingId, ['notify_customer' => true])->assertOk();
        $this->assertSame(8, (int) $order->orderProducts->first()->variant->fresh()->quantity);
        $this->assertTrue($order->fresh()->isFinished());
        Notification::assertSentToTimes($order, OrderExpedition::class, 1);
        $custom = $order->orderProducts->firstWhere('is_custom', true);
        $this->assertEquals(0, $custom->stocks()->first()->inventory_delta);
        $dispatchPreview = $this->postJson($url.'/preview', ['shipping_id' => $shippingId])->assertOk()->json('html');
        $mail = (new OrderExpedition($order, $order->shippings()->first()))->toMail($order);
        $this->assertSame($dispatchPreview, view($mail->view, $mail->viewData)->render());
        $this->deleteJson($url.'/'.$shippingId)->assertUnprocessable();
    }

    public function test_preparation_can_be_cancelled_and_changed_rows_cannot_be_dispatched(): void
    {
        $payload = $this->payload();
        $this->staff();
        $id = $this->postJson('/api/orders', $payload)->assertSuccessful()->json('data.id');
        $order = Order::findOrFail($id);
        $item = $order->orderProducts->first();
        $url = '/api/orders/'.$id.'/shippings';
        $shippingId = $this->postJson($url, ['items' => [['order_product_id' => $item->id, 'quantity' => 2]], 'notify_customer' => false])->assertSuccessful()->json('data.id');
        Notification::assertNotSentTo($order, OrderPreparing::class);
        $item->update(['storno' => 1]);
        $this->putJson($url.'/'.$shippingId)->assertUnprocessable();
        $this->assertDatabaseCount('stocks', 0);
        $this->deleteJson($url.'/'.$shippingId)->assertNoContent();
        $this->assertDatabaseCount('shippings', 0);
    }

    public function test_discount_is_capped_and_invalid_adjustments_are_rejected(): void
    {
        $payload = $this->payload();
        $this->staff();
        $id = $this->postJson('/api/orders', $payload)->assertSuccessful()->json('data.id');
        $this->putJson('/api/orders/'.$id, ['price_adjustment' => ['direction' => 'discount', 'type' => 'fixed', 'value' => 500]])
            ->assertOk()->assertJsonPath('data.adjustment_amount', -20)->assertJsonPath('data.grand_total', 4);
        $this->putJson('/api/orders/'.$id, ['price_adjustment' => ['direction' => 'discount', 'type' => 'percent', 'value' => 101]])->assertUnprocessable();
        $this->putJson('/api/orders/'.$id, ['price_adjustment' => ['direction' => 'surcharge', 'type' => 'fixed', 'value' => -1]])->assertUnprocessable();
        $this->putJson('/api/orders/'.$id, ['price_adjustment' => ['direction' => 'surcharge', 'type' => 'percent', 'value' => 10]])
            ->assertOk()->assertJsonPath('data.grand_total', 26);
    }

    public function test_custom_line_can_be_added_and_edited_on_existing_order(): void
    {
        $payload = $this->payload();
        $this->staff();
        $id = $this->postJson('/api/orders', $payload)->assertSuccessful()->json('data.id');
        $url = '/api/orders/'.$id.'/orderProducts';
        $item = $this->postJson($url, ['is_custom' => true, 'name' => 'Grafika', 'unit_value' => 'úkon', 'quantity' => 1, 'price' => 25, 'vat' => 20])->assertSuccessful()->json('data.id');
        $this->putJson($url.'/'.$item, ['product_id' => null, 'name' => 'Grafický návrh', 'quantity' => 2, 'price' => 20])->assertNoContent();
        $this->getJson('/api/orders/'.$id)->assertOk()->assertJsonPath('grand_total', 64);
        $this->deleteJson($url.'/'.$item)->assertNoContent();
    }
}
