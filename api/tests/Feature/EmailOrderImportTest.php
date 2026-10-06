<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Product;
use App\Models\User;
use App\Services\Companies\CompanyRegistry;
use App\Services\OpenAI\EmailOrderAI;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class EmailOrderImportTest extends TestCase
{
    use RefreshDatabase;

    private function login(string $role = 'super-admin'): void
    {
        $user = User::factory()->create();
        $user->assignRole($role);
        Sanctum::actingAs($user);
        config(['services.openai.key' => 'test-only']);
        Http::preventStrayRequests();
    }

    private function draft(array $customer = [], array $items = []): array
    {
        return ['customer' => array_replace(array_fill_keys(EmailOrderAI::FIELDS, ''), $customer), 'items' => $items, 'note' => '', 'warnings' => []];
    }

    private function preview()
    {
        return $this->postJson('/api/email-order-preview', ['text' => 'Dobrý deň, objednávame zástavu SR 100 x 150 cm.']);
    }

    public function test_guest_and_regular_admin_cannot_import(): void
    {
        $this->preview()->assertUnauthorized();
        $this->login('admin');
        $this->preview()->assertForbidden();
        Http::assertNothingSent();
    }

    public function test_missing_key_and_invalid_input_are_reported(): void
    {
        $this->login();
        $this->postJson('/api/email-order-preview', ['text' => 'x'])->assertUnprocessable();
        config(['services.openai.key' => '']);
        $this->preview()->assertStatus(503);
        Http::assertNothingSent();
    }

    public function test_existing_customer_and_catalog_prices_are_used_without_writes(): void
    {
        $this->login();
        $customer = Customer::create(['company' => 'Obec Test', 'email' => 'obec@example.test', 'ico' => '00123456', 'dic' => '2020123456', 'street' => 'Hlavná 1', 'city' => 'Test', 'postcode' => '12345', 'phone' => '0900123456']);
        $product = Product::create(['name' => 'Zástava SR', 'vat' => 23, 'published' => true]);
        $variant = $product->variants()->create(['code' => 'EMAIL-SR-100', 'name' => '100 × 150 cm', 'published' => true, 'price' => 25, 'min_order' => 1]);
        $this->mock(EmailOrderAI::class, function ($mock) use ($variant) {
            $mock->shouldReceive('extract')->once()->andReturn($this->draft(['email' => 'obec@example.test', 'name' => 'Starosta'], [['description' => 'SR 100 x 150', 'variant_id' => $variant->id, 'quantity' => 2]]));
            $mock->shouldNotReceive('research');
        });
        $this->mock(CompanyRegistry::class)->shouldReceive('find')->with('00123456')->once()->andReturn(null);
        $this->preview()->assertOk()->assertJsonPath('data.customer.id', $customer->id)
            ->assertJsonPath('data.customer_status', 'existing')->assertJsonPath('data.customer.dic', '2020123456')
            ->assertJsonPath('data.items.0.cart.variant_id', $variant->id)->assertJsonPath('data.items.0.cart.active_price', 25)
            ->assertJsonPath('data.items.0.cart.input_order', 2);
        $this->assertDatabaseCount('orders', 0);
        $this->assertDatabaseCount('customers', 1);
    }

    public function test_web_enrichment_new_client_and_unknown_variant_remain_reviewable(): void
    {
        $this->login();
        $this->mock(EmailOrderAI::class, function ($mock) {
            $mock->shouldReceive('extract')->once()->andReturn($this->draft(['email' => 'obec@example.test'], [['description' => 'Zástava nejasného prevedenia', 'variant_id' => 999999, 'quantity' => 1]]));
            $mock->shouldReceive('research')->once()->andReturn(['confirmed' => true, 'customer' => ['company' => 'Obec Test', 'ico' => '00123456'], 'sources' => ['https://example.test/kontakt', 'javascript:alert(1)'], 'warnings' => []]);
        });
        $this->mock(CompanyRegistry::class)->shouldReceive('find')->with('00123456')->once()->andReturn(['dic' => '2020123456', 'city' => 'Test']);
        $this->preview()->assertOk()->assertJsonPath('data.customer_status', 'new')
            ->assertJsonPath('data.customer.dic', '2020123456')->assertJsonPath('data.items.0.cart', null)
            ->assertJsonCount(2, 'data.sources');
        $this->assertDatabaseCount('customers', 0);
        $this->assertDatabaseCount('orders', 0);
    }

    public function test_conflicting_database_identity_is_not_attached_and_web_failure_is_partial(): void
    {
        $this->login();
        Customer::create(['company' => 'Iný subjekt', 'email' => 'obec@example.test', 'ico' => '00888888', 'postcode' => '12345', 'city' => 'Test']);
        $this->mock(EmailOrderAI::class, function ($mock) {
            $mock->shouldReceive('extract')->once()->andReturn($this->draft(['email' => 'obec@example.test', 'ico' => '00123456']));
            $mock->shouldReceive('research')->once()->andThrow(new \RuntimeException('offline'));
        });
        $this->mock(CompanyRegistry::class)->shouldReceive('find')->andReturn(null);
        $response = $this->preview()->assertOk()->assertJsonPath('data.customer_status', 'ambiguous');
        $this->assertArrayNotHasKey('id', $response->json('data.customer'));
        $this->assertNotEmpty($response->json('data.warnings'));
    }

    public function test_responses_adapter_uses_web_and_does_not_store_email(): void
    {
        $this->login();
        Http::fake(['api.openai.com/v1/responses' => Http::response(['status' => 'completed', 'output' => [
            ['type' => 'web_search_call'],
            ['type' => 'message', 'content' => [['type' => 'output_text', 'text' => json_encode(['confirmed' => false, 'customer' => [], 'sources' => [], 'warnings' => []])]]],
        ]])]);
        $this->assertFalse(app(EmailOrderAI::class)->research(['company' => 'Obec Test'])['confirmed']);
        Http::assertSent(fn ($request) => $request['store'] === false && $request['tools'][0]['type'] === 'web_search' && $request['text']['format']['strict'] === true);
    }
}
