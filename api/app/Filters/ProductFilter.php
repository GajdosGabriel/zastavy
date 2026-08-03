<?php

namespace App\Filters;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class ProductFilter extends Filters
{

    protected $filters = [
        'bySearchInput',
        'isUnpublished',
        'byCategory',
        'isDeleted',
        'byAttribute',
        'inStock',
        'priceFrom',
        'priceTo',
    ];

    public function bySearchInput($value)
    {
        return $this->builder->where(function (Builder $query) use ($value) {
            $query->where('code', 'like', '%' . $value . '%')
                ->orWhere('name', 'like', '%' . $value . '%')
                ->orWhere('description', 'like', '%' . $value . '%')
                ->orWhereHas('variants', fn (Builder $variants) => $variants
                    ->where('code', 'like', '%' . $value . '%')
                    ->orWhere('ean', 'like', '%' . $value . '%'));
        });
    }

    public function isUnpublished($value)
    {
        return $this->builder->where('published', $value ? 0 : $value );
    }

    public function isDeleted()
    {
        return $this->builder->onlyTrashed();
    }

    public function byCategory($id)
    {
        return $this->builder->whereHas('categories', function ($query) use ($id) {
            $query->whereId($id);
        });
    }

    /**
     * Fasetový filter nad taxonómiou.
     *
     * Prijíma buď byAttribute[rozmer][]=100x150, alebo
     * byAttribute=rozmer:100x150|100x70,material:polyester.
     *
     * V rámci jednej vlastnosti platí OR, medzi vlastnosťami AND — to je to,
     * čo zákazník čaká: „100x150 alebo 100x70, ale iba polyester“.
     */
    public function byAttribute($value)
    {
        foreach ($this->normalizeFacets($value) as $attributeCode => $valueCodes) {
            $this->builder->whereExists(function ($query) use ($attributeCode, $valueCodes) {
                $query->select(DB::raw(1))
                    ->from('attribute_value_product')
                    ->join('attribute_values', 'attribute_values.id', '=', 'attribute_value_product.attribute_value_id')
                    ->join('attributes', 'attributes.id', '=', 'attribute_values.attribute_id')
                    ->whereColumn('attribute_value_product.product_id', 'products.id')
                    ->where('attributes.code', $attributeCode)
                    ->where(function ($inner) use ($valueCodes) {
                        $inner->whereIn('attribute_values.code', $valueCodes)
                            ->orWhereIn('attribute_values.slug', $valueCodes);
                    });
            });
        }

        return $this->builder;
    }

    /**
     * Dostupné = aspoň jeden publikovaný variant, ktorý nie je vypredaný.
     * quantity NULL znamená, že sklad sa nesleduje — taký variant je dostupný.
     */
    public function inStock($value)
    {
        if (! filter_var($value, FILTER_VALIDATE_BOOLEAN)) {
            return $this->builder;
        }

        return $this->builder->whereHas('variants', fn (Builder $query) => $query
            ->where('published', true)
            ->where(fn (Builder $inner) => $inner->whereNull('quantity')->orWhere('quantity', '>', 0)));
    }

    public function priceFrom($value)
    {
        return $this->builder->whereHas('variants', fn (Builder $query) => $query
            ->where('published', true)
            ->whereRaw('COALESCE(NULLIF(sale_price, 0), price) >= ?', [(float) $value]));
    }

    public function priceTo($value)
    {
        return $this->builder->whereHas('variants', fn (Builder $query) => $query
            ->where('published', true)
            ->whereRaw('COALESCE(NULLIF(sale_price, 0), price) <= ?', [(float) $value]));
    }

    /**
     * @return array<string, array<int, string>>
     */
    private function normalizeFacets($value): array
    {
        if (is_array($value)) {
            return collect($value)
                ->map(fn ($codes) => array_values(array_filter((array) $codes)))
                ->filter()
                ->all();
        }

        $facets = [];

        foreach (explode(',', (string) $value) as $group) {
            [$attribute, $codes] = array_pad(explode(':', $group, 2), 2, null);
            $attribute = trim((string) $attribute);

            if ($attribute === '' || $codes === null) {
                continue;
            }

            $parsed = array_values(array_filter(array_map('trim', explode('|', $codes))));

            if ($parsed) {
                $facets[$attribute] = $parsed;
            }
        }

        return $facets;
    }
}
