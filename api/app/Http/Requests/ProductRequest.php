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
            'name' => 'required|min:2',
            'price' => 'required',
            'sale_price' => 'numeric|nullable',
            'min_order' => 'required',
            'published' => 'required|boolean',
            'discount' => 'numeric|nullable',
            'quantity' => 'numeric|nullable',
            'description' => 'string|nullable',
            'vat' => ['required', new VatRule()],
            'status' => ['sometimes', Rule::in(ModelStatus::allowedValuesForUser($this->user()))],
        ];
    }

    public function messages()
    {
        return [
            'name.required' => 'Názov produktu musí mať minimálne 2 znaky.',
            'min_order.required' => 'Minimálna objednaný počet musí byť vyplnený',
        ];
    }
}
