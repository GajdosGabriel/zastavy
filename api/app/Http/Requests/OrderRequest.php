<?php

namespace App\Http\Requests;

use Date;
use Carbon\Carbon;
use App\Notifications\OrderExpedition;
use App\Notifications\OrderDeliverySurvey;
use App\Services\ShippingService;
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
                'orderProducts.*.id' => ['required'],
                'orderProducts.*.input_order' => ['required', 'numeric', 'min:1'],
            ];
        }

        return [
            // 'customer' => 'required|exists:customers,id',
        ];
    }

    public function isFinished($order)
    {

        if (!$order->isOpened) {
            $order->update(['isOpened' => 1]);
        }
    }
}
