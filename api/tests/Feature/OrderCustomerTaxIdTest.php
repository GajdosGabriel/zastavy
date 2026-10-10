<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Order;
use App\Models\User;
use App\Services\Companies\CompanyRegistry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class OrderCustomerTaxIdTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Notification::fake();
    }

    private function order(array $customer = []): Order
    {
        $customer = Customer::create($customer + ['company' => 'Obec Test', 'email' => 'obec@example.test',
            'street' => 'Hlavná 1', 'city' => 'Nitra', 'postcode' => '94901', 'ico' => '00307181']);

        return Order::create(['customer_id' => $customer->id, 'name' => 'Kontakt', 'email' => 'obec@example.test']);
    }

    private function actAsSuperAdmin(): void
    {
        $user = User::factory()->create();
        $user->assignRole(Role::findOrCreate('super-admin'));
        Sanctum::actingAs($user);
    }

    public function test_missing_dic_is_filled_into_customer_but_not_into_order(): void
    {
        $order = $this->order();
        $this->mock(CompanyRegistry::class)->shouldReceive('find')->once()
            ->andReturn(['dic' => '2021102853', 'ic_dic' => 'SK2021102853']);
        $this->actAsSuperAdmin();

        $this->postJson('/api/orders/'.$order->id.'/customer-tax-ids')
            ->assertOk()
            ->assertJson(['filled' => ['dic', 'ic_dic'], 'dic' => '2021102853']);

        $this->assertSame('2021102853', $order->customer->fresh()->dic);
        $this->assertNull($order->fresh()->billing_snapshot['dic'] ?? null);

        $this->getJson('/api/orders/'.$order->id)
            ->assertOk()
            ->assertJsonPath('customer_current.dic', '2021102853')
            ->assertJsonPath('billing.dic', null);
    }

    public function test_existing_dic_is_never_overwritten(): void
    {
        $order = $this->order(['dic' => '1111111111']);
        $this->mock(CompanyRegistry::class)->shouldNotReceive('find');
        $this->actAsSuperAdmin();

        $this->postJson('/api/orders/'.$order->id.'/customer-tax-ids')
            ->assertOk()
            ->assertJson(['filled' => [], 'dic' => '1111111111']);
    }

    public function test_guest_cannot_trigger_lookup(): void
    {
        $this->postJson('/api/orders/'.$this->order()->id.'/customer-tax-ids')->assertUnauthorized();
    }
}
