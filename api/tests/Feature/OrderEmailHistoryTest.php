<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Models\Customer;
use App\Models\Order;
use App\Models\OrderEmail;
use App\Models\User;
use App\Notifications\OrderCreated;
use App\Support\OrderThread;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class OrderEmailHistoryTest extends TestCase
{
    use RefreshDatabase;

    private function order(): Order
    {
        $customer = Customer::create(['company' => 'Obec Test', 'email' => 'obec@example.test',
            'street' => 'Hlavná 1', 'city' => 'Nitra', 'postcode' => '94901']);
        $order = Order::create(['customer_id' => $customer->id, 'name' => 'Kontakt', 'email' => 'obec@example.test', 'serial_number' => '2026100']);
        $order->orderProducts()->create(['is_custom' => true, 'quantity' => 3, 'storno' => 0, 'price' => 10, 'total' => 30,
            'product_snapshot' => ['name' => 'Vlajka obce', 'unit_value' => 'ks', 'code' => null, 'vat' => 23, 'variant_name' => null, 'variant_code' => null]]);

        return $order;
    }

    private function actAsSuperAdmin(): User
    {
        $user = User::factory()->create(['email' => 'admin@example.test']);
        $user->assignRole(Role::findOrCreate('super-admin'));
        Sanctum::actingAs($user);

        return $user;
    }

    public function test_customer_emails_are_recorded_but_admin_copies_are_not(): void
    {
        $order = $this->order();
        $admin = $this->actAsSuperAdmin();

        $order->notifyCustomer(new OrderCreated($order));
        Notification::send($admin, new OrderCreated($order));

        $email = OrderEmail::sole();
        $this->assertSame($order->id, $email->order_id);
        $this->assertSame('OrderCreated', $email->type);
        $this->assertSame('obec@example.test', $email->recipient);
        $this->assertStringContainsString('Vlajka obce', $email->body);

        $this->getJson("/api/orders/{$order->id}/emails")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.label', 'Potvrdenie objednávky')
            ->assertJsonPath('data.0.status', 'sent')
            ->assertJsonPath('recipient', 'obec@example.test')
            ->assertJsonMissingPath('data.0.body');

        $this->getJson("/api/orders/{$order->id}/emails/{$email->id}")
            ->assertOk()->assertJsonPath('html', $email->body);
    }

    public function test_resent_confirmation_gets_own_message_id_in_the_order_thread(): void
    {
        $order = $this->order();
        $this->actAsSuperAdmin();

        $this->postJson("/api/orders/{$order->id}/emails", ['type' => 'confirmation'])->assertStatus(202);

        $sent = collect(Mail::mailer()->getSymfonyTransport()->messages())->sole();
        $this->assertNotSame(OrderThread::id($order), $sent->getMessageId());
        $this->assertStringContainsString(OrderThread::id($order), $sent->getOriginalMessage()->getHeaders()->get('References')->getBodyAsString());
        $this->assertSame('OrderCreated', OrderEmail::sole()->type);
    }

    public function test_item_storno_can_be_announced_later_with_a_note(): void
    {
        $order = $this->order();
        $this->actAsSuperAdmin();
        $order->notifyCustomer(new OrderCreated($order));

        $this->travel(1)->minutes();
        $order->orderProducts()->first()->update(['storno' => 1]);

        $this->getJson("/api/orders/{$order->id}/emails")->assertJsonPath('changed_since_last_email', true);

        $preview = $this->postJson("/api/orders/{$order->id}/emails/preview", ['type' => 'update', 'note' => 'Jeden kus nie je skladom.'])
            ->assertOk()->json('html');
        $this->assertStringContainsString('Jeden kus nie je skladom.', $preview);
        $this->assertStringContainsString('z toho storno 1', $preview);

        $this->postJson("/api/orders/{$order->id}/emails", ['type' => 'update', 'note' => 'Jeden kus nie je skladom.'])->assertStatus(202);

        $this->travel(1)->minutes();
        $this->getJson("/api/orders/{$order->id}/emails")
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.label', 'Zmena objednávky')
            ->assertJsonPath('changed_since_last_email', false);
    }

    public function test_shipping_notice_is_resent_and_marked_on_the_delivery_note(): void
    {
        $order = $this->order();
        $shipping = $order->shippings()->create([]);
        $this->actAsSuperAdmin();

        $options = $this->getJson("/api/orders/{$order->id}/emails")->json('options');
        $this->assertContains('Príprava zásielky – DL #'.$shipping->id, array_column($options, 'label'));
        $this->assertNotContains('cancelled', array_column($options, 'type'));

        $this->postJson("/api/orders/{$order->id}/emails", ['type' => 'shipping', 'shipping_id' => $shipping->id])->assertStatus(202);

        $this->assertSame('preparation_email', $shipping->notices()->sole()->notice);
        $this->assertSame('OrderPreparing', OrderEmail::sole()->type);
    }

    public function test_timeline_combines_delivery_notes_and_emails_newest_first(): void
    {
        $order = $this->order();
        $this->actAsSuperAdmin();
        $order->notifyCustomer(new OrderCreated($order));

        $this->travel(1)->hours();
        $shipping = $order->shippings()->create(['prepared_items' => [['order_product_id' => 1, 'name' => 'Vlajka obce', 'quantity' => 2, 'unit' => 'ks']]]);
        $this->postJson("/api/orders/{$order->id}/emails", ['type' => 'shipping', 'shipping_id' => $shipping->id])->assertStatus(202);

        $timeline = $this->getJson("/api/orders/{$order->id}/emails")->json('timeline');

        $this->assertSame(['E-mail: Príprava zásielky', 'DL #'.$shipping->id.' vystavený', 'E-mail: Potvrdenie objednávky', 'Objednávka prijatá'],
            array_column($timeline, 'label'));
        $this->assertSame('Vlajka obce × 2', $timeline[1]['detail']);
        $this->assertSame('pending', $timeline[1]['status']);
        $this->assertNotNull($timeline[0]['email_id']);
    }

    public function test_cancellation_only_for_cancelled_order(): void
    {
        $order = $this->order();
        $this->actAsSuperAdmin();

        $this->postJson("/api/orders/{$order->id}/emails", ['type' => 'cancelled'])->assertStatus(422);

        $order->forceFill(['status' => OrderStatus::Cancelled])->save();
        $this->postJson("/api/orders/{$order->id}/emails", ['type' => 'cancelled'])->assertStatus(202);
        $this->assertSame('OrderCancelled', OrderEmail::sole()->type);
    }

    public function test_history_requires_staff(): void
    {
        $order = $this->order();
        Sanctum::actingAs(User::factory()->create());

        $this->getJson("/api/orders/{$order->id}/emails")->assertForbidden();
        $this->postJson("/api/orders/{$order->id}/emails", ['type' => 'confirmation'])->assertForbidden();
    }
}
