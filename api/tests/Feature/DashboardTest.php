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

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    private Customer $customer;

    protected function setUp(): void
    {
        parent::setUp();

        Role::findOrCreate('super-admin', 'web');
        $staff = User::factory()->create(['status' => ModelStatus::Active->value]);
        $staff->assignRole('super-admin');
        Sanctum::actingAs($staff);

        $this->customer = Customer::create([
            'slug'     => 'firma',
            'company'  => 'Firma s.r.o.',
            'postcode' => '01001',
            'city'     => 'Žilina',
            'status'   => ModelStatus::Active->value,
        ]);
    }

    private function makeOrder(array $attributes, int $quantity, int $storno = 0, int $shipped = 0, float $price = 10): Order
    {
        $order = Order::create($attributes + [
            'customer_id'   => $this->customer->id,
            'serial_number' => 'T' . fake()->unique()->numberBetween(1000, 9999),
            'isOpened'      => 1,
        ]);

        $product = Product::firstOrCreate(
            ['code' => 'VLAJKA'],
            ['name' => 'Vlajka SR', 'vat' => 20, 'published' => 1],
        );

        $orderProduct = $order->orderProducts()->create([
            'product_id' => $product->id,
            'quantity'   => $quantity,
            'storno'     => $storno,
            'price'      => $price,
            'total'      => $price * $quantity,
        ]);

        if ($shipped > 0) {
            $shipping = $order->shippings()->create([]);
            $order->stocks()->create([
                'order_product_id' => $orderProduct->id,
                'shipping_id'      => $shipping->id,
                'quantity'         => $shipped,
            ]);
        }

        return $order->fresh();
    }

    public function test_dashboard_counts_queues_sales_and_waiting_orders(): void
    {
        $unopened = $this->makeOrder(['isOpened' => 0], 5);
        $partial  = $this->makeOrder([], 4, 0, 1);
        $this->makeOrder([], 2, 0, 2);                                        // vybavená
        $this->makeOrder(['status' => OrderStatus::Cancelled->value], 3);     // storno
        $old = $this->makeOrder([], 1);
        $old->forceFill(['created_at' => now()->subDays(10)])->save();

        $response = $this->getJson('/api/dashboard')->assertOk();

        $response->assertJsonPath('data.queue.unopened', 1)
            ->assertJsonPath('data.queue.open', 3)
            ->assertJsonPath('data.queue.overdue', 1)
            ->assertJsonPath('data.queue.partially_shipped', 1);

        // Najstaršia čakajúca ide prvá, vybavená a stornovaná sa nezobrazia.
        $this->assertSame(
            [$old->id, $unopened->id, $partial->id],
            array_column($response->json('data.waiting'), 'id'),
        );
        $response->assertJsonPath('data.waiting.0.days_waiting', 10);

        // Dnešné objednávky bez storna: 5 + 4 + 2 ks po 10 €.
        $response->assertJsonPath('data.sales.today.current.order_count', 3)
            ->assertJsonPath('data.sales.today.current.value', 110)
            ->assertJsonPath('data.sales.today.shipped.value', 30);

        $this->assertCount(30, $response->json('data.daily'));
        $this->assertSame(now()->toDateString(), $response->json('data.daily.29.date'));
        $response->assertJsonPath('data.daily.29.order_count', 3);

        // Chýba 5 + 3 + 1 ks z otvorených objednávok.
        $response->assertJsonPath('data.missing_products.0.remaining_quantity', 9)
            ->assertJsonPath('data.missing_products.0.order_count', 3);
    }

    public function test_dashboard_requires_dashboard_access(): void
    {
        Sanctum::actingAs(User::factory()->create(['status' => ModelStatus::Active->value]));

        $this->getJson('/api/dashboard')->assertForbidden();
    }
}
