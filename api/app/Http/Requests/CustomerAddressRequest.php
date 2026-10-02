<?php

namespace App\Http\Requests;

use App\Rules\CustomerPhone;
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
            // Normalizácia by z nezmyslu spravila „00012" alebo null (a ten SQL chybu),
            // preto sa tvar stráži tu — rovnako ako pri fakturačnej adrese.
            'postcode'   => ['required', 'string', 'regex:/^\d{3}\s?\d{2}$/'],
            'city'       => ['required', 'string', 'max:100'],
            'country'    => ['nullable', 'string', 'size:2'],
            // Na toto číslo volá kuriér — nečitateľné je horšie než žiadne.
            'phone'      => ['nullable', 'string', 'max:40', new CustomerPhone()],
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
            'postcode.regex'    => __('rules.postcode.invalid'),
        ];
    }

    public function attributes(): array
    {
        return [
            'label'   => 'pomenovanie',
            'company' => 'názov príjemcu',
            'name'    => 'kontaktná osoba',
            'phone'   => 'telefón',
            'note'    => 'poznámka',
        ];
    }
}
