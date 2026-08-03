<?php

namespace App\Http\Requests;

use App\Enums\ModelStatus;
use App\Models\ProductVariant;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class ProductVariantRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'code'       => [
                'sometimes',
                'nullable',
                'string',
                'max:100',
                'regex:/^[A-Za-z0-9._\\-\\/]+$/',
                Rule::unique('product_variants', 'code')->ignore($this->route('variant')),
            ],
            'ean'        => [
                'nullable',
                'string',
                'max:32',
                'regex:/^[0-9]+$/',
                Rule::unique('product_variants', 'ean')->ignore($this->route('variant')),
            ],
            'price'      => 'required|numeric|min:0',
            'sale_price' => 'nullable|numeric|min:0|lte:price',
            'discount'   => 'nullable|numeric|min:0|max:100',
            'quantity'   => 'nullable|integer|min:0',
            'weight'     => 'nullable|numeric|min:0',
            'min_order'  => 'sometimes|integer|min:1',
            'image_id'   => 'nullable|integer|exists:images,id',
            'is_default' => 'sometimes|boolean',
            'published'  => 'sometimes|boolean',
            'sort_order' => 'sometimes|integer|min:0',
            'status'     => ['sometimes', Rule::in(ModelStatus::allowedValuesForUser($this->user()))],

            'attribute_values'   => 'present|array',
            'attribute_values.*' => 'integer|exists:attribute_values,id',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $this->assertOneValuePerAttribute($validator);
            $this->assertCombinationIsFree($validator);
        });
    }

    /**
     * Variant nesmie mať dve hodnoty tej istej vlastnosti (2× Rozmer).
     */
    private function assertOneValuePerAttribute(Validator $validator): void
    {
        $ids = collect($this->input('attribute_values', []))->filter()->unique();

        if ($ids->isEmpty()) {
            return;
        }

        $perAttribute = DB::table('attribute_values')
            ->whereIn('id', $ids)
            ->pluck('attribute_id')
            ->countBy();

        if ($perAttribute->contains(fn ($count) => $count > 1)) {
            $validator->errors()->add(
                'attribute_values',
                'Variant môže mať od každej vlastnosti najviac jednu hodnotu.'
            );
        }
    }

    /**
     * Rovnaká kombinácia hodnôt sa v rámci produktu nesmie opakovať —
     * inak by sa nedalo určiť, ktorý variant zákazník vybral.
     */
    private function assertCombinationIsFree(Validator $validator): void
    {
        $product = $this->route('product');

        if (! $product) {
            return;
        }

        $ids = collect($this->input('attribute_values', []))
            ->filter()->unique()->sort()->values();

        $current = $this->route('variant');

        $duplicate = ProductVariant::where('product_id', $product->id)
            ->when($current, fn ($query) => $query->whereKeyNot($current->id))
            ->with('attributeValues:id')
            ->get()
            ->first(fn (ProductVariant $variant) => $variant->attributeValues
                ->pluck('id')->sort()->values()->all() === $ids->all());

        if ($duplicate) {
            $validator->errors()->add(
                'attribute_values',
                'Variant s touto kombináciou už na produkte existuje (' . $duplicate->code . ').'
            );
        }
    }

    public function messages(): array
    {
        return [
            'price.required'   => 'Cena variantu musí byť vyplnená.',
            'sale_price.lte'   => 'Akciová cena nesmie byť vyššia ako bežná cena.',
            'ean.regex'        => 'EAN smie obsahovať len čísla.',
            'code.unique'      => 'Variant s týmto kódom už existuje.',
        ];
    }
}
