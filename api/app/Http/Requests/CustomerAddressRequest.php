<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CustomerAddressRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'label'      => ['nullable', 'string', 'max:100'],
            'company'    => ['nullable', 'string', 'max:200'],
            'name'       => ['nullable', 'string', 'max:150'],
            'street'     => ['required', 'string', 'max:250'],
            'postcode'   => ['required', 'string', 'max:20'],
            'city'       => ['required', 'string', 'max:100'],
            'country'    => ['nullable', 'string', 'size:2'],
            'phone'      => ['nullable', 'string', 'max:40'],
            'note'       => ['nullable', 'string', 'max:255'],
            'is_default' => ['nullable', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'street.required'   => 'Vyplňte ulicu a číslo.',
            'postcode.required' => 'Vyplňte PSČ.',
            'city.required'     => 'Vyplňte mesto.',
        ];
    }
}
