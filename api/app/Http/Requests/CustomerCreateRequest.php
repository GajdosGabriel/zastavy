<?php

namespace App\Http\Requests;

use App\Enums\CustomerType;
use App\Rules\CustomerPhone;
use App\Rules\CustomerTaxId;
use App\Rules\IcoRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use App\Http\Requests\CustomerUpdateRequest;

class CustomerCreateRequest extends CustomerUpdateRequest
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
            // Dĺžky kopírujú stĺpce v databáze — dlhší text by skončil SQL chybou.
            'company' => 'required|string|min:2|max:200',
            'name' => 'nullable|string|max:150',
            'email' => 'required|email|max:150',
            'note' => 'nullable|string|max:255',
            'phone' => ['nullable', new CustomerPhone()],
            'street' => 'nullable|string|max:250',
            // Stĺpce `postcode` a `city` sú v databáze NOT NULL — bez nich by
            // uloženie skončilo SQL chybou, tak sa vyžadujú už tu.
            'postcode' => $this->postcodeRules(),
            'city' => 'required|string|max:100',
            // IcoRule stráži jedinečnosť, CustomerTaxId správnosť — sú to dve
            // rôzne otázky a obe treba položiť.
            'ico' => ['nullable', new IcoRule(), new CustomerTaxId('ico')],
            'dic' => ['nullable', new CustomerTaxId('dic')],
            'ic_dic' => ['nullable', new CustomerTaxId('ic_dic')],
            // Prázdny typ = určí ho klasifikátor podľa názvu a IČO.
            'type' => ['nullable', Rule::enum(CustomerType::class)],
        ];
    }

    public function messages()
    {
        return [
            'company.required' => __('rules.company.min'),
            'company.min' => __('rules.company.min'),
            'postcode.regex' => __('rules.postcode.invalid'),
            'ico.max' => __('rules.ico.length'),
        ];
    }
}
