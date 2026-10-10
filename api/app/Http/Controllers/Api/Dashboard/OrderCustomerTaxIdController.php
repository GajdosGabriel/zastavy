<?php

namespace App\Http\Controllers\Api\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Services\Customers\CustomerReviewService;
use Illuminate\Support\Facades\Gate;

/**
 * Dohľadá zákazníkovi objednávky chýbajúce DIČ / IČ DPH v registri.
 *
 * Zapisuje sa len do `customers` — odtlačok objednávky ostáva taký, aký
 * prišiel. Autorizuje sa cez expedíciu objednávky, lebo práve ona DIČ
 * potrebuje a k správe zákazníkov prístup mať nemusí.
 */
class OrderCustomerTaxIdController extends Controller
{
    public function __invoke(Order $order, CustomerReviewService $service)
    {
        Gate::authorize('ship', $order);

        $customer = $order->customer;
        $changes = $customer === null ? [] : $service->fillMissingTaxIds($customer);

        return response()->json([
            'filled' => array_column($changes, 'field'),
            'dic' => $customer?->dic,
            'ic_dic' => $customer?->ic_dic,
        ]);
    }
}
