<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class CatalogQueryTest extends TestCase
{
    use RefreshDatabase;

    private function product(int $id): void
    {
        $product = Product::create(['name' => 'Query '.$id, 'code' => 'QUERY-'.$id, 'vat' => 20, 'published' => true]);
        $image = $product->images()->create(['name' => 'test', 'path' => 'test.jpg', 'disk' => 'public']);
        $product->variants()->create(['code' => 'QUERY-V-'.$id, 'published' => true, 'price' => 10, 'image_id' => $image->id, 'is_default' => true]);
        $product->categories()->attach(Category::create(['name' => 'Category '.$id])->id);
    }

    private function queries(): int
    {
        DB::enableQueryLog();
        DB::flushQueryLog();
        try {
            $this->getJson('/api/homes')->assertOk()->assertJsonPath('data.0.categories.0.name', 'Category 1');

            return count(DB::getQueryLog());
        } finally {
            DB::disableQueryLog();
            DB::flushQueryLog();
        }
    }

    public function test_more_products_do_not_add_per_product_queries(): void
    {
        $this->product(1);
        $one = $this->queries();
        foreach (range(2, 6) as $id) {
            $this->product($id);
        }
        $six = $this->queries();
        $this->assertSame($one, $six, 'Počet SQL dotazov nesmie rásť s počtom produktov.');
        $this->assertLessThanOrEqual(10, $six);
    }
}
