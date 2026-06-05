<?php

namespace App\Http\Controllers;

use Carbon\Carbon;
use App\Models\Order;
use App\Models\Customer;
use App\Models\OrderProduct;
use Illuminate\Support\Str;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class TestController extends Controller
{
//     UPDATE orderProducts
// SET  product_id = 3
//  WHERE product_id = 2
    public function test()
    {
        $order = Order::first();

        $shipping = $order->shippings()->create();


        foreach ($order->orderProducts as $item) {
        //   dd( $shipping->stocks);
          $shipping->stocks()->create(['order_product_id' => $item->id]);
        }



        // for ($x = 1201; $x <= 1500; $x++) {
        //     $order =  DB::table('oldorders')->find($x);

        //     if ($order) {
        //         $this->getCustomer($order);
        //     }
        // }
    }


    public function getCustomer($order)
    {

        $customer = DB::table('oldcustomers')->whereId($order->customer_id)->first();

        if ($customerNovy = DB::table('customers')->whereIco($customer->ico)->first()) {

            $orderNew =  Order::create([
                'customer_id' => $customerNovy->id,
                'serial_number' => '',
                'notice' => '',
                'isFinished' => 1,
                'isStorned' => 0,
                'isOpened' => $order->created_at,
                'created_at' => $order->created_at,
                'updated_at' => $order->updated_at
            ]);

            $this->orderProducts($orderNew, $order);
        } else {

            $customer =  Customer::create([
                "name" => $customer->lastname,
                "company" => $customer->firma,
                'slug' => Str::slug($customer->firma, '-'),
                'phone' => $customer->phone,
                'email' => $customer->email,
                'street' => $customer->street,
                'postcode' => $customer->psc,
                'city' => $customer->city,
                'ico' => $customer->ico,
                'dic' => $customer->dic,
            ]);

            $orderNew = Order::create([
                'customer_id' => $customer->id,
                'serial_number' => '',
                'notice' => '',
                'isFinished' => 1,
                'isStorned' => 0,
                'isOpened' => $order->created_at,
                'created_at' => $order->created_at,
                'updated_at' => $order->updated_at
            ]);

            $this->orderProducts($orderNew, $order);
        }
    }

    public function orderProducts($order, $orderOld)
    {


        $items = DB::table('oldorder_items')->whereOrderId($orderOld->id)->get();

        // dd($items);
        foreach ($items as $item) {
            
            if($item->product_id == 2) {
                $item->product_id = 3;
            } elseif ($item->product_id == 3) {
                $item->product_id = 2;
            } 


            OrderProduct::create([
                'order_id' => $order->id,
                'product_id' => $item->product_id,
                'quantity' => $item->quantity,
                'price' => $item->price,
                'sent_at' => Carbon::create( $item->created_at)->addDays(2),
                'created_at' => $item->created_at,
                'updated_at' => $item->updated_at
            ]);
        }
    }
}
