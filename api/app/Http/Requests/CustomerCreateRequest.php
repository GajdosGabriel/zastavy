<?php

namespace App\Http\Requests;

use App\Rules\IcoRule;
use Illuminate\Foundation\Http\FormRequest;
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
            'company'=>'required|min:2',
            'ico' => new IcoRule(),
        ];
    }

    public function messages()
    {
        return [
            'company.required' => 'Firma musí obsahovať minimálne 2 znaky.',
            'ico.max' => 'IČO nesmie mať viac ako 8 znakov',
        ];
    }
}
