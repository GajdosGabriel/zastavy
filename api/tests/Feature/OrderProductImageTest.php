<?php

namespace Tests\Feature;

use App\Http\Resources\OrderProductResource;
use App\Models\Image;
use App\Models\OrderProduct;
use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Mockery;
use Tests\TestCase;

class OrderProductImageTest extends TestCase
{
    private function thumbnail(?Image $productImage, ?Image $variantImage = null, bool $withVariant = false): string
    {
        $product = new Product();
        $product->setRelation('images', collect($productImage ? [$productImage] : []));
        $variant = null;
        if ($withVariant) {
            $variant = new ProductVariant();
            $variant->setRelation('image', $variantImage);
            $variant->setRelation('product', $product);
        }
        $item = new OrderProduct([
            'id' => 1, 'order_id' => 2401, 'quantity' => 1, 'storno' => 0,
            'product_snapshot' => ['name' => 'Vlajka', 'unit_value' => 'ks', 'vat' => 23, 'variant_code' => null],
        ]);
        $item->setRelation('product', $product);
        $item->setRelation('variant', $variant);
        $item->setRelation('stocks', collect());

        return (new OrderProductResource($item))->toArray(Request::create('/'))['thumb'];
    }

    public function test_local_product_image_keeps_its_path_without_duplicating_api_prefix(): void
    {
        config(['app.url' => 'https://example.test/web']);
        app('url')->forceRootUrl('https://example.test/web');
        $disk = Mockery::mock();
        $disk->shouldReceive('url')->with('vlajka.jpg')->andReturn('https://example.test/web/storage/vlajka.jpg');
        Storage::shouldReceive('disk')->with('public')->andReturn($disk);

        $image = new Image(['disk' => 'public', 'path' => 'public/vlajka.jpg']);
        $this->assertSame('/web/storage/vlajka.jpg', $this->thumbnail($image));
        $this->assertSame('/web/storage/vlajka.jpg', $this->thumbnail($image, withVariant: true));
    }

    public function test_variant_image_preserves_signed_s3_url(): void
    {
        config(['filesystems.disks.s3.driver' => 's3', 'media.public_read' => false]);
        $url = 'https://bucket.example.test/variant.jpg?X-Amz-Signature=abc&X-Amz-Expires=3600';
        $disk = Mockery::mock();
        $disk->shouldReceive('temporaryUrl')->with('variant.jpg', Mockery::any())->andReturn($url);
        Storage::shouldReceive('disk')->with('s3')->andReturn($disk);

        $this->assertSame($url, $this->thumbnail(
            new Image(['disk' => 'public', 'path' => 'product.jpg']),
            new Image(['disk' => 's3', 'path' => 'variant.jpg']),
            true,
        ));
    }

    public function test_missing_image_uses_frontend_placeholder(): void
    {
        $this->assertSame('/images/product-placeholder.svg', $this->thumbnail(null));
    }
}
