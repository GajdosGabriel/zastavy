<?php

namespace App\Http\Requests;

use App\Enums\ModelStatus;
use App\Rules\VatRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ProductRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        return [
            'code' => [
                'required',
                'string',
                'max:64',
                'regex:/^[A-Za-z0-9._\\-\\/]+$/',
                Rule::unique('products', 'code')->ignore($this->route('product')),
            ],
            'name' => 'required|min:2',
            'published' => 'required|boolean',
            'description' => 'string|nullable',
            'featured' => 'sometimes|boolean',
            'unit_value' => ['sometimes', Rule::in(['ks', 'l', 'kg'])],
            'vat' => ['required', new VatRule()],
            'status' => ['sometimes', Rule::in(ModelStatus::allowedValuesForUser($this->user()))],
            'categories' => 'sometimes|array',
            'categories.*' => 'integer|exists:categories,id',
        ];
    }

    public function messages()
    {
        return [
            'name.required' => 'Názov produktu musí mať minimálne 2 znaky.',
        ];
    }
}
