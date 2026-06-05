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
