<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use Carbon\Carbon;
use Illuminate\Http\Request;

class OrderDeliverySurveyController extends Controller
{
    public function show(Order $orderDeliverySurvey, Request $request)
    {
        if ($request->delivery == 1) {
            $orderDeliverySurvey->update(['isDelivered' => 1 ]);
        }
        if ($request->delivery == 0) {
            $orderDeliverySurvey->update(['isDelivered' => 0 ]);
        }
        return redirect('thank-you-delivery-survey');
    }
}
