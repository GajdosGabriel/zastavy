<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AttributeValueRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $attribute = $this->route('attribute');

        return [
            'value'      => 'required|string|min:1|max:191',
            'code'       => [
                'sometimes',
                'string',
                'max:100',
                Rule::unique('attribute_values', 'code')
                    ->where('attribute_id', $attribute?->id)
                    ->ignore($this->route('value')),
            ],
            'color'      => 'nullable|string|max:32',
            'sort_order' => 'sometimes|integer|min:0',
        ];
    }

    public function messages(): array
    {
        return [
            'value.required' => 'Hodnota musí byť vyplnená.',
            'code.unique'    => 'Táto hodnota už pri danej vlastnosti existuje.',
        ];
    }
}
