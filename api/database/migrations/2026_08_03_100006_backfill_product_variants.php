<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Z každého existujúceho produktu spraví jeden variant a naparsuje
     * doterajší reťazec products.attributes do taxonómie.
     *
     * Zámerne pracuje cez DB fasádu, nie cez modely — migrácia musí prejsť
     * aj keď sa modely v budúcnosti zmenia.
     */
    public function up(): void
    {
        $products = DB::table('products')->get();

        if ($products->isEmpty()) {
            return;
        }

        $now = now();

        $rozmerId = $this->attributeId('rozmer', 'Rozmer', 'cm', 0, $now);
        $prevedenieId = $this->attributeId('prevedenie', 'Prevedenie', null, 1, $now);

        foreach ($products as $product) {
            $valueId = $this->resolveValueId($product->attributes, $rozmerId, $prevedenieId, $now);

            $variantId = DB::table('product_variants')->insertGetId([
                'status'     => $product->status ?? 'active',
                'product_id' => $product->id,
                'code'       => $this->variantCode($product, $valueId),
                'name'       => $this->variantName($valueId),
                'price'      => $product->price ?? 0,
                'sale_price' => ($product->sale_price ?? 0) > 0 ? $product->sale_price : null,
                'discount'   => $product->discount,
                'quantity'   => $product->quantity,
                'weight'     => $product->weight,
                'min_order'  => max(1, (int) ($product->min_order ?? 1)),
                'image_id'   => $product->image_id,
                'is_default' => true,
                'published'  => $product->published,
                'sort_order' => 0,
                'created_at' => $product->created_at ?? $now,
                'updated_at' => $now,
                'deleted_at' => $product->deleted_at,
            ]);

            if ($valueId) {
                DB::table('attribute_value_product_variant')->insert([
                    'product_variant_id' => $variantId,
                    'attribute_value_id' => $valueId,
                ]);

                DB::table('attribute_value_product')->insert([
                    'product_id'        => $product->id,
                    'attribute_value_id' => $valueId,
                    'is_variant_option' => true,
                ]);

                $attributeId = DB::table('attribute_values')->where('id', $valueId)->value('attribute_id');

                DB::table('attribute_product')->insert([
                    'product_id'   => $product->id,
                    'attribute_id' => $attributeId,
                    'sort_order'   => 0,
                ]);
            }

            // Historické objednávky a skladové pohyby prilepíme na default variant,
            // inak by po dropnutí products.price stratili väzbu na skladovú položku.
            DB::table('order_products')
                ->where('product_id', $product->id)
                ->update([
                    'product_variant_id' => $variantId,
                    'variant_label'      => $this->variantName($valueId),
                ]);

            DB::table('stocks')
                ->where('product_id', $product->id)
                ->update(['product_variant_id' => $variantId]);
        }
    }

    public function down(): void
    {
        DB::table('stocks')->update(['product_variant_id' => null]);
        DB::table('order_products')->update(['product_variant_id' => null, 'variant_label' => null]);
        DB::table('attribute_product')->delete();
        DB::table('attribute_value_product')->delete();
        DB::table('attribute_value_product_variant')->delete();
        DB::table('product_variants')->delete();
        DB::table('attribute_values')->delete();
        DB::table('attributes')->whereIn('code', ['rozmer', 'prevedenie'])->delete();
    }

    private function attributeId(string $code, string $name, ?string $unit, int $sort, $now): int
    {
        $existing = DB::table('attributes')->where('code', $code)->value('id');

        if ($existing) {
            return $existing;
        }

        return DB::table('attributes')->insertGetId([
            'status'        => 'active',
            'code'          => $code,
            'name'          => $name,
            'unit'          => $unit,
            'input_type'    => 'select',
            'is_variant'    => true,
            'is_filterable' => true,
            'is_public'     => true,
            'sort_order'    => $sort,
            'created_at'    => $now,
            'updated_at'    => $now,
        ]);
    }

    /**
     * "100x150cm" → Rozmer / 100 × 150 cm; čokoľvek iné spadne pod Prevedenie.
     */
    private function resolveValueId(?string $raw, int $rozmerId, int $prevedenieId, $now): ?int
    {
        $raw = trim((string) $raw);

        if ($raw === '') {
            return null;
        }

        if (preg_match('/^(\d+)\s*[x×]\s*(\d+)\s*(cm|mm|m)?$/iu', $raw, $m)) {
            $unit = strtolower($m[3] ?? 'cm');

            return $this->valueId(
                $rozmerId,
                $m[1] . 'x' . $m[2],
                $m[1] . ' × ' . $m[2] . ' ' . $unit,
                $now
            );
        }

        return $this->valueId($prevedenieId, Str::slug($raw), $raw, $now);
    }

    private function valueId(int $attributeId, string $code, string $value, $now): int
    {
        $existing = DB::table('attribute_values')
            ->where('attribute_id', $attributeId)
            ->where('code', $code)
            ->value('id');

        if ($existing) {
            return $existing;
        }

        return DB::table('attribute_values')->insertGetId([
            'attribute_id' => $attributeId,
            'code'         => $code,
            'value'        => $value,
            'slug'         => Str::slug($value),
            'sort_order'   => 0,
            'created_at'   => $now,
            'updated_at'   => $now,
        ]);
    }

    private function variantName(?int $valueId): ?string
    {
        return $valueId
            ? DB::table('attribute_values')->where('id', $valueId)->value('value')
            : null;
    }

    private function variantCode($product, ?int $valueId): string
    {
        $base = $product->code ?: 'TOV-' . str_pad((string) $product->id, 6, '0', STR_PAD_LEFT);

        if (! $valueId) {
            return Str::limit($base, 100, '');
        }

        $suffix = DB::table('attribute_values')->where('id', $valueId)->value('code');

        return Str::limit($base . '-' . Str::upper($suffix), 100, '');
    }
};
