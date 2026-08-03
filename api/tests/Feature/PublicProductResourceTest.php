<?php

namespace Tests\Feature;

use App\Enums\ModelStatus;
use App\Models\Announcement;
use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class PublicProductResourceTest extends TestCase
{
    use RefreshDatabase;

    private function makeProduct(): Product
    {
        $product = Product::create([
            'name' => 'Vlajka SR 100x150',
            'code' => 'VSR-100',
            'vat' => 20,
            'published' => 1,
        ]);

        $product->variants()->create([
            'code' => 'VSR-100-100X150',
            'name' => '100 × 150 cm',
            'price' => 25.50,
            'min_order' => 1,
            'is_default' => true,
            'published' => 1,
        ]);

        $category = Category::create(['name' => 'Štátne vlajky', 'slug' => 'statne-vlajky']);
        $product->categories()->attach($category);

        return $product;
    }

    /**
     * Verejný detail produktu nesmie posielať mapu admin routes ani blok permissions —
     * je to zbytočná mapa administrácie a navyše záťaž odpovede.
     */
    public function test_public_product_does_not_expose_admin_routes(): void
    {
        $product = $this->makeProduct();

        $response = $this->getJson('/api/products/' . $product->id);

        // `products.show` vracia resource bez `data` obálky.
        $response->assertOk()
            ->assertJsonPath('endpoints', [])
            ->assertJsonPath('permissions', [])
            ->assertJsonPath('variants.0.endpoints', [])
            ->assertJsonPath('variants.0.permissions', [])
            ->assertJsonPath('categories.0.url', []);

        // Žiadna admin route sa nesmie objaviť ani inde v odpovedi.
        $this->assertStringNotContainsString('"destroy"', $response->getContent());
        $this->assertStringNotContainsString('"update"', $response->getContent());
    }

    public function test_staff_still_gets_admin_routes_and_permissions(): void
    {
        $product = $this->makeProduct();

        Role::findOrCreate('super-admin', 'web');
        $staff = User::factory()->create();
        $staff->assignRole('super-admin');
        Sanctum::actingAs($staff);

        $response = $this->getJson('/api/products/' . $product->id);

        $response->assertOk()
            ->assertJsonPath('endpoints.update', route('products.update', $product->id))
            ->assertJsonPath('permissions.update.allowed', true)
            ->assertJsonPath('categories.0.url.update', route('categories.update', $product->categories->first()->id));
    }

    /**
     * Prihlásený zákazník nie je personál — mapu administrácie nedostane ani on.
     */
    public function test_portal_customer_does_not_get_admin_routes(): void
    {
        $product = $this->makeProduct();

        Sanctum::actingAs(User::factory()->create());

        $this->getJson('/api/products/' . $product->id)
            ->assertOk()
            ->assertJsonPath('endpoints', [])
            ->assertJsonPath('permissions', []);
    }

    public function test_public_announcements_do_not_expose_admin_routes(): void
    {
        Announcement::create([
            'placement' => 'top',
            'title' => 'Doprava zadarmo',
            'body' => 'Nad 100 €',
            'status' => ModelStatus::Active,
        ]);

        $this->getJson('/api/announcements/active')
            ->assertOk()
            ->assertJsonPath('data.0.endpoints', []);
    }
}
