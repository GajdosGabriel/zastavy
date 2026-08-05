<?php

namespace Tests\Feature;

use App\Enums\ModelStatus;
use App\Enums\OrderStatus;
use App\Models\Customer;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class StatusFilterTest extends TestCase
{
    use RefreshDatabase;

    private function actingAsSuperAdmin(): User
    {
        Role::findOrCreate('super-admin', 'web');
        $staff = User::factory()->create(['status' => ModelStatus::Active->value]);
        $staff->assignRole('super-admin');
        Sanctum::actingAs($staff);

        return $staff;
    }

    private function makeCustomer(string $company, ModelStatus $status): Customer
    {
        return Customer::create([
            'name'     => $company,
            'slug'     => str($company)->slug()->toString(),
            'company'  => $company,
            'postcode' => '01001',
            'city'     => 'Žilina',
            'status'   => $status->value,
        ]);
    }

    private function makeOrder(Customer $customer, OrderStatus $status, int $quantity, int $storno = 0, int $shipped = 0): Order
    {
        $order = Order::create([
            'customer_id'   => $customer->id,
            'serial_number' => 'T' . fake()->unique()->numberBetween(1000, 9999),
            'status'        => $status->value,
            'isOpened'      => 1,
        ]);

        $product = Product::create([
            'name'      => 'Vlajka ' . $order->serial_number,
            'code'      => 'V-' . $order->serial_number,
            'vat'       => 20,
            'published' => 1,
        ]);

        $orderProduct = $order->orderProducts()->create([
            'product_id' => $product->id,
            'quantity'   => $quantity,
            'storno'     => $storno,
            'price'      => 10,
            'total'      => 10 * $quantity,
        ]);

        if ($shipped > 0) {
            $order->stocks()->create([
                'order_product_id' => $orderProduct->id,
                'quantity'         => $shipped,
            ]);
        }

        return $order->fresh();
    }

    public function test_customers_index_filters_by_status_and_returns_status_options(): void
    {
        $this->actingAsSuperAdmin();

        $active   = $this->makeCustomer('Aktivna firma', ModelStatus::Active);
        $archived = $this->makeCustomer('Archivna firma', ModelStatus::Archived);

        $response = $this->getJson('/api/customers?status=' . ModelStatus::Archived->value)->assertOk();

        $response->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $archived->id);

        // Pagination meta musí zostať vedľa statusov.
        $this->assertSame(1, $response->json('meta.current_page'));
        $this->assertContains(
            ModelStatus::Archived->value,
            array_column($response->json('meta.statuses'), 'value')
        );

        // Bez filtra vidno oboch.
        $this->getJson('/api/customers')->assertOk()->assertJsonCount(2, 'data');

        // Neznámy status filter ignoruje.
        $this->getJson('/api/customers?status=nonsense')->assertOk()->assertJsonCount(2, 'data');

        $this->assertSame(ModelStatus::Active->value, $active->fresh()->status->value);
    }

    public function test_users_index_filters_by_status_and_returns_status_options(): void
    {
        $this->actingAsSuperAdmin();

        $draft = User::factory()->create(['status' => ModelStatus::Draft->value]);

        $response = $this->getJson('/api/users?status=' . ModelStatus::Draft->value)->assertOk();

        $response->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $draft->id);

        $this->assertSame(1, $response->json('meta.current_page'));
        $this->assertContains(
            ModelStatus::Draft->value,
            array_column($response->json('meta.statuses'), 'value')
        );

        // Super-admin aj draft user — bez filtra dvaja.
        $this->getJson('/api/users')->assertOk()->assertJsonCount(2, 'data');
        $this->getJson('/api/users?status=nonsense')->assertOk()->assertJsonCount(2, 'data');
    }

    public function test_orders_index_filters_by_computed_status(): void
    {
        $this->actingAsSuperAdmin();
        $customer = $this->makeCustomer('Objednavkova firma', ModelStatus::Active);

        $processing = $this->makeOrder($customer, OrderStatus::Draft, quantity: 5);
        $partially  = $this->makeOrder($customer, OrderStatus::Draft, quantity: 5, shipped: 2);
        $shipped    = $this->makeOrder($customer, OrderStatus::Draft, quantity: 5, shipped: 5);
        $cancelled  = $this->makeOrder($customer, OrderStatus::Cancelled, quantity: 5);
        $archived   = $this->makeOrder($customer, OrderStatus::Archived, quantity: 5);

        $expected = [
            OrderStatus::Processing->value       => $processing->id,
            OrderStatus::PartiallyShipped->value => $partially->id,
            OrderStatus::Shipped->value          => $shipped->id,
            OrderStatus::Cancelled->value        => $cancelled->id,
            OrderStatus::Archived->value         => $archived->id,
        ];

        foreach ($expected as $status => $orderId) {
            $response = $this->getJson('/api/orders?status=' . $status)->assertOk();

            $response->assertJsonCount(1, 'data')
                ->assertJsonPath('data.0.id', $orderId)
                // Filter musí sedieť s tým, čo je v zozname vypísané.
                ->assertJsonPath('data.0.status.value', $status);
        }

        $response = $this->getJson('/api/orders')->assertOk();
        $response->assertJsonCount(5, 'data');
        $this->assertSame(1, $response->json('meta.current_page'));
        $this->assertContains(
            OrderStatus::PartiallyShipped->value,
            array_column($response->json('meta.statuses'), 'value')
        );

        // Neznámy status filter ignoruje.
        $this->getJson('/api/orders?status=nonsense')->assertOk()->assertJsonCount(5, 'data');
    }
}
