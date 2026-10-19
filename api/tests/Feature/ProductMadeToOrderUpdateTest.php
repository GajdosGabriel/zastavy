<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ProductMadeToOrderUpdateTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Vypredaný produkt (záporný sklad) sa dá vo formulári prepnúť na „na zákazku“
     * rovnakým payloadom, aký posiela admin UI (StoreProducts::productPayload).
     */
    public function test_sold_out_product_can_be_switched_to_made_to_order(): void
    {
        $product = Product::create(['name' => 'Štátny znak SR na stenu', 'code' => 'TOV-000006', 'vat' => 23, 'published' => 1]);
        $product->variants()->create([
            'code' => 'TOV-000006-33X27', 'name' => '33 × 27', 'price' => 30, 'min_order' => 1,
            'is_default' => true, 'published' => 1, 'quantity' => -4,
        ]);

        Role::findOrCreate('super-admin', 'web');
        $staff = User::factory()->create();
        $staff->assignRole('super-admin');
        Sanctum::actingAs($staff);

        $loaded = $this->getJson('/api/products/' . $product->id)->assertOk()->json();

        $this->putJson($loaded['endpoints']['update'], [
            'name' => $loaded['name'],
            'code' => $loaded['code'],
            'description' => $loaded['description'],
            'vat' => $loaded['vat'],
            'unit_value' => $loaded['unit_value'],
            'published' => $loaded['published'],
            'featured' => $loaded['featured'] ?? false,
            'made_to_order' => true,
            'categories' => [],
            'status' => $loaded['status']['value'] ?? $loaded['status'],
        ])->assertOk()->assertJsonPath('data.made_to_order', true);

        $this->assertTrue($product->fresh()->made_to_order);

        // Starší formulár posielal späť vypočítaný stav „vypredaný“ — nesmie produkt zamraziť.
        $this->assertSame('out_of_stock', $loaded['status']['value']);

        $this->getJson('/api/homes/' . $product->id)
            ->assertOk()
            ->assertJsonPath('made_to_order', true)
            ->assertJsonPath('status.value', 'active')
            ->assertJsonPath('is_in_stock', true)
            ->assertJsonPath('variants.0.is_in_stock', true);
    }
}
