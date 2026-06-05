<?php

namespace App\Filters;

use App\Models\Order;
use App\Models\Customer;
use Illuminate\Http\Request;
use Illuminate\Database\Eloquent\Builder;

class CustomerFilter extends Filters
{

    protected $filters = ['sortByOrders', 'bySearchInput', 'sortById', 'isMarked', 'withoutOrder'];

    public function bySearchInput($company)
    {
        return $this->builder->where(function ($query) use ($company) {
            $query->where('company', 'like', '%' . $company . '%')
                ->orWhere('city', 'like', '%' . $company . '%')
                ->orWhere('ico', 'like', '%' . $company . '%')
                ->orWhere('postcode', 'like', '%' . $company . '%')
                ->orWhere('email', 'like', '%' . $company . '%')
                ->orWhereHas('users', function ($query) use ($company) {
                    $query->where('username', 'like', '%' . $company . '%')
                        ->orWhere('firstName', 'like', '%' . $company . '%')
                        ->orWhere('lastName', 'like', '%' . $company . '%')
                        ->orWhere('email', 'like', '%' . $company . '%')
                        ->orWhere('phone', 'like', '%' . $company . '%');
                });
        });
    }

    public function sortById($value)
    {
        return $this->builder->orderBy('id', 'desc');
    }

    public function isMarked()
    {
        return $this->builder->whereHas('mark');
    }


    public function sortByOrders()
    {
        return $this->builder->withCount('orders')->orderByDesc('orders_count');
    }

    public function withoutOrder()
    {
        return $this->builder->doesntHave('orders');
    }
}
