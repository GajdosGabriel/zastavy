<?php

namespace App\Services;

use App\Models\Attribute;
use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Support\Facades\DB;

class ProductVariantService
{
    /**
     * Priradí variantu kombináciu hodnôt, prepočíta jeho popis a fasetový
     * index produktu. Volať vždy po zmene variantu — index sa inak rozíde.
     *
     * @param  array<int, int>  $attributeValueIds
     */
    public function syncAttributeValues(ProductVariant $variant, array $attributeValueIds): ProductVariant
    {
        DB::transaction(function () use ($variant, $attributeValueIds) {
            $variant->attributeValues()->sync(array_values(array_unique($attributeValueIds)));
            $variant->load('attributeValues.attribute');
            $variant->refreshLabel();

            $this->rebuildProductIndex($variant->product);
        });

        return $variant->refresh();
    }

    /**
     * Prepočíta odvodené riadky fasetového indexu z variantov produktu.
     * Ručne priradené hodnoty (is_variant_option = 0) zostávajú nedotknuté.
     */
    public function rebuildProductIndex(Product $product): void
    {
        $valueIds = DB::table('attribute_value_product_variant')
            ->join('product_variants', 'product_variants.id', '=', 'attribute_value_product_variant.product_variant_id')
            ->where('product_variants.product_id', $product->id)
            ->whereNull('product_variants.deleted_at')
            ->distinct()
            ->pluck('attribute_value_product_variant.attribute_value_id');

        DB::transaction(function () use ($product, $valueIds) {
            DB::table('attribute_value_product')
                ->where('product_id', $product->id)
                ->where('is_variant_option', true)
                ->delete();

            if ($valueIds->isNotEmpty()) {
                DB::table('attribute_value_product')->insertOrIgnore(
                    $valueIds->map(fn ($id) => [
                        'product_id'         => $product->id,
                        'attribute_value_id' => $id,
                        'is_variant_option'  => true,
                    ])->all()
                );
            }

            $this->syncUsedAttributes($product, $valueIds->all());
        });
    }

    /**
     * @param  array<int, int>  $valueIds
     */
    private function syncUsedAttributes(Product $product, array $valueIds): void
    {
        $attributeIds = empty($valueIds)
            ? collect()
            : DB::table('attribute_values')->whereIn('id', $valueIds)->distinct()->pluck('attribute_id');

        $sortOrder = Attribute::whereIn('id', $attributeIds)->pluck('sort_order', 'id');

        $product->attributesTaxonomy()->sync(
            $attributeIds->mapWithKeys(fn ($id) => [
                $id => ['sort_order' => (int) ($sortOrder[$id] ?? 0)],
            ])->all()
        );
    }

    /**
     * Kód variantu odvodený z kódu produktu a kombinácie hodnôt.
     */
    public function generateCode(Product $product, array $attributeValueIds): string
    {
        $suffix = DB::table('attribute_values')
            ->whereIn('id', $attributeValueIds)
            ->orderBy('attribute_id')
            ->pluck('code')
            ->implode('-');

        $base = $suffix === '' ? $product->code : $product->code . '-' . $suffix;
        $code = mb_strtoupper(mb_substr($base, 0, 100));

        // Kolízie vznikajú pri duplikovaní variantu — dorovnáme poradovým číslom.
        $candidate = $code;
        $i = 2;
        while (ProductVariant::withTrashed()->where('code', $candidate)->exists()) {
            $suffixNum = '-' . $i++;
            $candidate = mb_substr($code, 0, 100 - mb_strlen($suffixNum)) . $suffixNum;
        }

        return $candidate;
    }
}
