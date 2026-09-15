<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class SecurityBoundariesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Notification::fake();
    }

    private function customer(): Customer
    {
        return Customer::create(['company' => 'Original', 'email' => 'original@example.test',
            'street' => 'Original 1', 'city' => 'Bratislava', 'postcode' => '81101', 'ico' => '12345678']);
    }

    private function product(bool $published = true): Product
    {
        $product = Product::create(['name' => 'Flag', 'code' => 'FLAG', 'published' => $published, 'vat' => 20]);
        $product->variants()->create(['code' => 'FLAG-A', 'name' => 'Public', 'price' => 10,
            'published' => true, 'is_default' => true, 'min_order' => 1]);

        return $product;
    }

    private function payload(Product $product): array
    {
        return ['customer' => ['company' => 'Submitted', 'name' => 'Contact', 'email' => 'new@example.test',
            'phone' => '0900123456', 'street' => 'New 2', 'city' => 'Nitra', 'postcode' => '94901', 'ico' => '12345678'],
            'orderProducts' => [['id' => $product->id, 'input_order' => 1]]];
    }

    #[DataProvider('publicActors')]
    public function test_checkout_does_not_overwrite_or_join_existing_company(bool $portal): void
    {
        $customer = $this->customer();
        $before = $customer->fresh()->getAttributes();
        $actor = null;
        if ($portal) {
            $actor = User::factory()->create(['customer_id' => $customer->id]);
            Sanctum::actingAs($actor);
        }
        $this->postJson('/api/checkouts', $this->payload($this->product()))->assertOk();
        $order = Order::firstOrFail();
        $this->assertNotEquals($customer->id, $order->customer_id);
        $this->assertSame($before, $customer->fresh()->getAttributes());
        $this->assertSame($portal ? 1 : 0, $customer->users()->count());
        if ($portal) {
            $this->assertEquals($actor->id, $order->user_id);
            $this->getJson('/api/orders/'.$order->id)->assertOk();
        }
    }

    public static function publicActors(): array
    {
        return [[false], [true]];
    }

    public function test_public_customer_and_address_ids_are_rejected(): void
    {
        $customer = $this->customer();
        $payload = $this->payload($this->product());
        $payload['customer']['id'] = $customer->id;
        $payload['customer_address_id'] = 1;
        $this->postJson('/api/checkouts', $payload)->assertUnprocessable()
            ->assertJsonValidationErrors(['customer.id', 'customer_address_id']);
        $this->assertDatabaseCount('orders', 0);
    }

    public function test_missing_customer_is_validation_error(): void
    {
        $this->postJson('/api/checkouts', [])->assertUnprocessable()->assertJsonValidationErrors('customer');
    }

    public function test_staff_can_select_existing_customer(): void
    {
        $customer = $this->customer();
        $user = User::factory()->create();
        $user->assignRole('super-admin');
        Sanctum::actingAs($user);
        $payload = $this->payload($this->product());
        $payload['customer']['id'] = $customer->id;
        $this->postJson('/api/checkouts', $payload)->assertOk();
        $this->assertEquals($customer->id, Order::firstOrFail()->customer_id);
    }

    #[DataProvider('roles')]
    public function test_internal_operations_require_specific_staff_permissions(?string $role, bool $ship, bool $update): void
    {
        $customer = $this->customer();
        $user = User::factory()->create(['customer_id' => $customer->id]);
        if ($role) {
            $user->assignRole($role);
        }
        Sanctum::actingAs($user);
        $order = Order::create(['customer_id' => $customer->id, 'user_id' => $user->id]);
        $product = $this->product();
        $order->orderProducts()->create(['product_id' => $product->id, 'product_variant_id' => $product->defaultVariant->id,
            'quantity' => 2, 'price' => 10, 'total' => 20]);
        $this->getJson('/api/orders/'.$order->id)->assertOk();
        $this->putJson('/api/orders/'.$order->id, ['status' => 'processing'])->assertStatus($update ? 200 : 403);
        $this->postJson('/api/orders/'.$order->id.'/shippings', ['notify_customer' => false])->assertStatus($ship ? 201 : 403);
        $return = $order->orderReturns()->create(['reason' => 'other', 'status' => 'pending', 'created_by' => $user->id]);
        $this->postJson('/api/orders/'.$order->id.'/returns/'.$return->id.'/process', ['notify_customer' => false])
            ->assertStatus($ship ? 200 : 403);
    }

    public static function roles(): array
    {
        return [[null, false, false], ['super-admin', true, true], ['admin', true, true],
            ['manager', true, true], ['sales', false, true], ['warehouse', true, false]];
    }

    public function test_portal_cannot_change_item_prices_or_create_returns(): void
    {
        $customer = $this->customer();
        $user = User::factory()->create(['customer_id' => $customer->id]);
        Sanctum::actingAs($user);
        $order = Order::create(['customer_id' => $customer->id]);
        $this->postJson('/api/orders/'.$order->id.'/orderProducts', [])->assertForbidden();
        $this->putJson('/api/orders/'.$order->id.'/orderProducts/999', ['price' => 0])->assertForbidden();
        $this->postJson('/api/orders/'.$order->id.'/returns', [])->assertForbidden();
    }

    #[DataProvider('blockedStates')]
    public function test_disabling_account_revokes_existing_tokens(array $changes): void
    {
        $user = User::factory()->create();
        $user->assignRole('super-admin');
        $token = $user->createToken('before')->plainTextToken;
        $user->update($changes);
        $this->assertSame(0, $user->tokens()->count());
        $this->withToken($token)->getJson('/api/orders')->assertUnauthorized();
    }

    public static function blockedStates(): array
    {
        return [[['active' => false]], [['status' => 'blocked']], [['status' => 'cancelled']], [['status' => 'archived']]];
    }

    public function test_middleware_rejects_disabled_account_even_without_observer(): void
    {
        $user = User::factory()->create();
        $user->assignRole('super-admin');
        $token = $user->createToken('before')->plainTextToken;
        User::whereKey($user->id)->update(['active' => false]);
        $this->withToken($token)->getJson('/api/orders')->assertForbidden();
    }

    #[DataProvider('publicActors')]
    public function test_hidden_products_and_variants_are_not_public(bool $portal): void
    {
        if ($portal) {
            Sanctum::actingAs(User::factory()->create(['customer_id' => $this->customer()->id]));
        }
        $product = $this->product(false);
        foreach (['products', 'homes'] as $endpoint) {
            $this->getJson('/api/'.$endpoint.'/'.$product->id)->assertNotFound();
        }
        $product->update(['published' => true]);
        $hidden = $product->variants()->create(['code' => 'SECRET', 'price' => 100, 'published' => false, 'is_default' => true]);
        $product->defaultVariant()->where('id', '!=', $hidden->id)->update(['is_default' => false]);
        foreach (['products', 'homes'] as $endpoint) {
            $this->getJson('/api/'.$endpoint.'/'.$product->id)->assertOk()
                ->assertJsonCount(1, 'variants')->assertJsonPath('default_variant', null)
                ->assertJsonMissing(['code' => 'SECRET']);
        }
        $this->getJson('/api/homes')->assertOk()->assertJsonMissing(['code' => 'SECRET']);
    }

    public function test_anonymous_cannot_call_internal_operations(): void
    {
        $this->postJson('/api/orders/1/shippings', [])->assertUnauthorized();
        $this->postJson('/api/orders/1/returns', [])->assertUnauthorized();
        $this->putJson('/api/orders/1', ['status' => 'archived'])->assertUnauthorized();
    }

    public function test_portal_permissions_do_not_grant_staff_operations_or_other_company_access(): void
    {
        $customer = $this->customer();
        $user = User::factory()->create(['customer_id' => $customer->id]);
        $user->givePermissionTo(['orders.view', 'orders.update', 'shippings.manage', 'orderProducts.manage']);
        Sanctum::actingAs($user);
        $own = Order::create(['customer_id' => $customer->id]);
        $other = Order::create(['customer_id' => $this->customer()->id]);
        $this->getJson('/api/orders/'.$other->id)->assertForbidden();
        $this->putJson('/api/orders/'.$own->id, ['status' => 'archived'])->assertForbidden();
        $this->postJson('/api/orders/'.$own->id.'/shippings', [])->assertForbidden();
        $this->postJson('/api/orders/'.$own->id.'/orderProducts', [])->assertForbidden();
    }

    public function test_item_update_cannot_target_a_different_order(): void
    {
        $user = User::factory()->create();
        $user->assignRole('super-admin');
        Sanctum::actingAs($user);
        $customer = $this->customer();
        $order = Order::create(['customer_id' => $customer->id]);
        $other = Order::create(['customer_id' => $customer->id]);
        $product = $this->product();
        $item = $other->orderProducts()->create(['product_id' => $product->id, 'quantity' => 1, 'price' => 10, 'total' => 10]);
        $this->putJson('/api/orders/'.$order->id.'/orderProducts/'.$item->id, ['price' => 0])->assertNotFound();
        $this->deleteJson('/api/orders/'.$order->id.'/orderProducts/'.$item->id)->assertNotFound();
        $this->assertEquals(10, $item->fresh()->price);
        $this->assertFalse($item->fresh()->trashed());
    }

    public function test_dashboard_checkout_cannot_bypass_company_id_restriction(): void
    {
        $customer = $this->customer();
        $user = User::factory()->create(['customer_id' => $customer->id]);
        Sanctum::actingAs($user);
        $payload = $this->payload($this->product());
        $payload['customer']['id'] = $customer->id;
        $this->postJson('/api/orders', $payload)->assertUnprocessable()->assertJsonValidationErrors('customer.id');
        $this->assertDatabaseCount('orders', 0);
    }

    public function test_revoked_token_cannot_preview_unpublished_product(): void
    {
        $user = User::factory()->create();
        $user->assignRole('super-admin');
        $token = $user->createToken('before')->plainTextToken;
        $user->update(['active' => false]);
        $this->withToken($token)->getJson('/api/products/'.$this->product(false)->id)->assertNotFound();
    }

    public function test_authorized_staff_can_preview_hidden_product(): void
    {
        $user = User::factory()->create();
        $user->assignRole('super-admin');
        Sanctum::actingAs($user);
        $product = $this->product(false);
        foreach (['products', 'homes'] as $endpoint) {
            $this->getJson('/api/'.$endpoint.'/'.$product->id)->assertOk();
        }
    }
}
