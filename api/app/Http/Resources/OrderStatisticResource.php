<?php

namespace App\Http\Resources;

use Carbon\Carbon;
use App\Http\Resources\NoticeResource;
use App\Http\Resources\CustomerResource;
use App\Http\Resources\ShippingResource;
use App\Http\Resources\OrderProductResource;
use Illuminate\Http\Resources\Json\JsonResource;
use App\Models\Order;

class OrderStatisticResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array|\Illuminate\Contracts\Support\Arrayable|\JsonSerializable
     */
    public function toArray($request)
    {
        return [
            'isConfirmed' => Order::where('isOpened', '=', false)->count(),
            'isNotificated' => Order::whereHas('shippings')->whereDoesntHave('shippings.notices')->count(),
            'isDeleted' => Order::onlyTrashed()->count() 
        ];
    }
}
