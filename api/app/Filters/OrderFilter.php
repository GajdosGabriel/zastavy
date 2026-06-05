<?php

namespace App\Filters;

use App\Models\Order;
use App\Models\Product;
use App\Models\Customer;
use App\Models\OrderProduct;
use Illuminate\Http\Request;
use Illuminate\Database\Eloquent\Builder;

class OrderFilter extends Filters
{

    protected $filters = ['isActive', 'bySearchInput', 'isOpened', 'isMarked', 'isDeleted', 'searchByProduct', 'isNotificated'];


    public function isActive($value)
    {
        return $this->builder->whereDoesntHave('stocks', function (Builder $query) {
            // return $this->builder->whereHas('stocks', function (Builder $query) {
            // $query->where('content', 'like', 'code%');
        });


        // dd( $collection->get() );
    }

    public function isOpened($value)
    {
        return $this->builder->where('isOpened', $value ? 0 : $value);
    }

    public function isMarked()
    {
        return $this->builder->whereHas('mark');
    }

    public function isNotificated()
    {
        return $this->builder->whereHas('shippings')->whereDoesntHave('shippings.notices');
    }

    public function isDeleted()
    {
        return $this->builder->onlyTrashed();
    }

    public function bySearchInput($company)
    {
        return $this->builder->whereHas('customer', function ($query) use ($company) {
            $query->where('company', 'like', '%' . $company . '%')
                ->orWhere('city', 'like', '%' . $company . '%')
                ->orWhere('ico', 'like', '%' . $company . '%');
        })->get();
    }

    public function searchByProduct($input)
    {
        $products =  Product::where('name', 'like', '%' . $input . '%')->get()->pluck('id');

        $orders =  OrderProduct::whereIn('product_id',  $products)->first()->pluck('id');

        // dd($orders);
        $this->builder->whereIn('id',  $orders)->get();
    }
}
