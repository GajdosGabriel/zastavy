<?php

namespace App\Http\Controllers\Api\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Http\Requests\ProductVariantRequest;
use App\Http\Resources\ProductVariantResource;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Services\ProductVariantService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class ProductVariantController extends Controller
{
    public function __construct(private ProductVariantService $variants)
    {
    }

    public function index(Product $product)
    {
        Gate::authorize('view', $product);

        $variants = $product->variants()->with('attributeValues.attribute')->get();

        // Bez rodiča by variant nevedel o príznaku "na zákazku" a hlásil by vypredané.
        $variants->each->setRelation('product', $product);

        return ProductVariantResource::collection($variants);
    }

    public function show(Product $product, ProductVariant $variant)
    {
        Gate::authorize('view', $variant);

        abort_unless($variant->product_id === $product->id, 404);

        return response(new ProductVariantResource($variant->load('attributeValues.attribute')));
    }

    public function store(Product $product, ProductVariantRequest $request)
    {
        Gate::authorize('create', ProductVariant::class);

        $data = $request->validated();
        $valueIds = $data['attribute_values'] ?? [];
        unset($data['attribute_values']);

        $variant = DB::transaction(function () use ($product, $data, $valueIds) {
            $data['code'] = ($data['code'] ?? null) ?: $this->variants->generateCode($product, $valueIds);

            $variant = $product->variants()->create($data);

            $this->ensureSingleDefault($product, $variant);
            $this->variants->syncAttributeValues($variant, $valueIds);

            return $variant;
        });

        return new ProductVariantResource($variant->load('attributeValues.attribute'));
    }

    public function update(Product $product, ProductVariant $variant, ProductVariantRequest $request)
    {
        Gate::authorize('update', $variant);

        abort_unless($variant->product_id === $product->id, 404);

        $data = $request->validated();
        $valueIds = $data['attribute_values'] ?? [];
        unset($data['attribute_values']);

        DB::transaction(function () use ($product, $variant, $data, $valueIds) {
            if (($data['code'] ?? null) === null) {
                unset($data['code']);
            }

            $variant->update($data);

            $this->ensureSingleDefault($product, $variant);
            $this->variants->syncAttributeValues($variant, $valueIds);
        });

        return new ProductVariantResource($variant->refresh()->load('attributeValues.attribute'));
    }

    public function destroy(Product $product, ProductVariant $variant)
    {
        Gate::authorize('delete', $variant);

        abort_unless($variant->product_id === $product->id, 404);

        DB::transaction(function () use ($product, $variant) {
            $variant->delete();

            // Po zmazaní posledného default variantu musí niektorý iný prevziať rolu,
            // inak by produkt nemal čo ponúknuť ako predvolenú voľbu.
            if ($variant->is_default) {
                $product->variants()->orderBy('sort_order')->first()?->forceFill(['is_default' => true])->save();
            }

            $this->variants->rebuildProductIndex($product);
        });

        return response()->noContent();
    }

    /**
     * Default variant je práve jeden — inak nevie karta produktu, čo predvyplniť.
     */
    private function ensureSingleDefault(Product $product, ProductVariant $variant): void
    {
        if ($variant->is_default) {
            $product->variants()
                ->whereKeyNot($variant->id)
                ->update(['is_default' => false]);

            return;
        }

        if (! $product->variants()->where('is_default', true)->exists()) {
            $variant->forceFill(['is_default' => true])->save();
        }
    }
}
