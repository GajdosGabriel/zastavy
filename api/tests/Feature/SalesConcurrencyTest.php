<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\Product;
use App\Models\SalesQuote;
use App\Models\ShippingMethod;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabaseState;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Support\RunsConcurrentProcesses;
use Tests\TestCase;

class SalesConcurrencyTest extends TestCase
{
    use RunsConcurrentProcesses;

    protected function setUp(): void
    {
        parent::setUp();
        if (DB::getDriverName() !== 'mysql') {
            $this->markTestSkipped('Requires independent MySQL processes.');
        }
        $this->artisan('migrate:fresh', ['--force' => true])->assertExitCode(0);
        RefreshDatabaseState::$migrated = false;
    }

    private function fixture(): array
    {
        $p = Product::create(['name' => 'Flag', 'code' => 'FLAG', 'vat' => 23, 'published' => true]);
        $v = $p->variants()->create(['code' => 'V', 'name' => 'Large', 'price' => 12, 'published' => true]);
        $m = ShippingMethod::create(['name' => 'Courier', 'price' => 5, 'active' => true]);
        $token = Str::random(64);
        $q = SalesQuote::create(['uuid' => (string) Str::uuid(), 'token_hash' => hash('sha256', $token), 'token_expires_at' => now()->addDays(90), 'version' => 1, 'status' => 'offered',
            'brief' => 'Concurrent quote', 'requested_items' => [], 'customer' => ['company' => 'Race buyer', 'name' => 'Buyer', 'email' => 'race@example.test', 'phone' => '0900123456', 'street' => 'Street 1', 'postcode' => '94901', 'city' => 'Nitra']]);
        $user = User::factory()->create();
        $user->assignRole('super-admin');
        $q->versions()->create(['version' => 1, 'valid_until' => today()->addDay(), 'created_by' => $user->id, 'shipping' => ['id' => $m->id, 'name' => $m->name, 'price' => 5],
            'items' => [['variant_id' => $v->id, 'product_id' => $p->id, 'quantity' => 2, 'price' => 9, 'snapshot' => ['name' => 'Flag', 'variant_name' => 'Large', 'unit_value' => 'ks', 'vat' => 23]]]]);

        return [$q, $token, $v, $m, $user];
    }

    public function test_two_acceptances_create_one_order(): void
    {
        [$q,$token] = $this->fixture();
        $job = ['worker' => 'tests/Support/sales-worker.php', 'url' => '/api/public-quotes/'.$q->uuid.'/accept', 'token' => $token, 'payload' => ['version' => 1, 'name' => 'Buyer', 'confirm' => true]];
        $results = $this->race($job, $job);
        $this->assertSame([200, 200], array_column($results, 'status'), json_encode($results));
        $this->assertSame($results[0]['body']['order_uuid'], $results[1]['body']['order_uuid']);
        $this->assertSame(1, Order::count());
    }

    public function test_new_version_and_acceptance_cannot_both_win(): void
    {
        [$q,$token,$v,$m,$user] = $this->fixture();
        $results = $this->race(['worker' => 'tests/Support/sales-worker.php', 'url' => '/api/public-quotes/'.$q->uuid.'/accept', 'token' => $token, 'payload' => ['version' => 1, 'name' => 'Buyer', 'confirm' => true]],
            ['worker' => 'tests/Support/sales-worker.php', 'user' => $user->id, 'url' => '/api/sales-quotes/'.$q->id.'/offer', 'payload' => ['version' => 1, 'items' => [['variant_id' => $v->id, 'quantity' => 2, 'price' => 15]], 'shipping_method_id' => $m->id, 'shipping_price' => 5, 'valid_until' => today()->addDay()->toDateString()]]);
        $this->assertEqualsCanonicalizing([200, 409], array_column($results, 'status'), json_encode($results));
        if ($results[0]['status'] === 200) {
            $this->assertEquals(9, Order::first()->orderProducts->first()->price);
            $this->assertSame(1, $q->fresh()->version);
        } else {
            $this->assertSame(0, Order::count());
            $this->assertSame(2, $q->fresh()->version);
        }
    }
}
