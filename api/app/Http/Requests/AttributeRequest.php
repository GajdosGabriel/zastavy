<?php

namespace App\Http\Requests;

use App\Enums\ModelStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AttributeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name'          => 'required|string|min:2|max:128',
            'code'          => [
                'sometimes',
                'string',
                'max:64',
                'regex:/^[a-z0-9_]+$/',
                Rule::unique('attributes', 'code')->ignore($this->route('attribute')),
            ],
            'unit'          => 'nullable|string|max:16',
            'input_type'    => ['sometimes', Rule::in(['select', 'color', 'text'])],
            'is_variant'    => 'sometimes|boolean',
            'is_filterable' => 'sometimes|boolean',
            'is_public'     => 'sometimes|boolean',
            'sort_order'    => 'sometimes|integer|min:0',
            'status'        => ['sometimes', Rule::in(ModelStatus::allowedValuesForUser($this->user()))],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Názov vlastnosti musí byť vyplnený.',
            'code.regex'    => 'Kód smie obsahovať len malé písmená, čísla a podčiarkovník.',
            'code.unique'   => 'Vlastnosť s týmto kódom už existuje.',
        ];
    }
}
