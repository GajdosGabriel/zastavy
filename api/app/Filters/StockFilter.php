<?php

namespace App\Filters;

use App\Models\Order;
use App\Models\Customer;
use App\Models\Product;
use Illuminate\Http\Request;

class StockFilter extends Filters
{

    protected $filters = ['bySearchInput'];

    public function bySearchInput($input)
    {
       $products =  Product::where('name', 'like', '%' . $input . '%')->get()->pluck('id');


        return $this->builder->whereIn('order_product_id',  $products )->get();
    }
}
