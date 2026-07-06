<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;


class OrderRequest extends FormRequest
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
        if ($this->has('customer')) {
            return [
                'customer' => ['required', 'array'],
                'customer.company' => ['required', 'string', 'min:2'],
                'customer.name' => ['required', 'string'],
                'customer.email' => ['required', 'email'],
                'customer.phone' => ['required', 'string'],
                'customer.street' => ['required', 'string'],
                'customer.postcode' => ['required'],
                'customer.city' => ['required', 'string'],
                'customer.ico' => ['nullable'],
                'customer.dic' => ['nullable'],
                'customer.ic_dic' => ['nullable'],
                'orderProducts' => ['required', 'array', 'min:1'],
                'orderProducts.*.id' => ['required', 'integer', 'exists:products,id'],
                'orderProducts.*.input_order' => ['required', 'integer', 'min:1', 'max:100000'],
                'note'         => ['nullable', 'string', 'max:1000'],
                'wants_coupon' => ['boolean'],
                'notify_customer'     => ['sometimes', 'boolean'],
                'shipping_method_id'  => ['nullable', 'exists:shipping_methods,id'],
                'payment_method_id'   => ['nullable', 'exists:payment_methods,id'],
                'coupon_code'         => ['nullable', 'string', 'max:50'],
            ];
        }

        return [
            'note' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
