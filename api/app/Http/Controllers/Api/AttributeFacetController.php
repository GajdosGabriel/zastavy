<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Attribute;
use Illuminate\Support\Facades\DB;

class AttributeFacetController extends Controller
{
    /**
     * Fasety pre verejný katalóg — len vlastnosti označené ako verejné
     * a filtrovateľné, s počtom produktov pri každej hodnote.
     */
    public function index()
    {
        $counts = DB::table('attribute_value_product')
            ->join('products', 'products.id', '=', 'attribute_value_product.product_id')
            ->where('products.published', 1)
            ->whereNull('products.deleted_at')
            ->groupBy('attribute_value_product.attribute_value_id')
            ->select('attribute_value_product.attribute_value_id', DB::raw('COUNT(DISTINCT products.id) as total'))
            ->pluck('total', 'attribute_value_id');

        $attributes = Attribute::query()
            ->where('is_public', true)
            ->filterable()
            ->with('values')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        $data = $attributes->map(function (Attribute $attribute) use ($counts) {
            $values = $attribute->values
                // Hodnota bez produktu by v katalógu viedla na prázdny výpis.
                ->filter(fn ($value) => ($counts[$value->id] ?? 0) > 0)
                ->map(fn ($value) => [
                    'id'    => $value->id,
                    'code'  => $value->code,
                    'value' => $value->value,
                    'slug'  => $value->slug,
                    'color' => $value->color,
                    'count' => (int) $counts[$value->id],
                ])
                ->values();

            return [
                'code'   => $attribute->code,
                'name'   => $attribute->name,
                'unit'   => $attribute->unit,
                'type'   => $attribute->input_type,
                'values' => $values,
            ];
        })->filter(fn ($attribute) => $attribute['values']->isNotEmpty())->values();

        return response()->json(['data' => $data]);
    }
}
