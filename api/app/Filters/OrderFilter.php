<?php

namespace App\Filters;

use Illuminate\Database\Eloquent\Builder;

class OrderFilter extends Filters
{

    protected $filters = ['isActive', 'bySearchInput', 'isOpened', 'isMarked', 'isDeleted', 'searchByProduct', 'isNotificated'];


    public function isActive($value)
    {
        return $this->builder->whereDoesntHave('stocks');
    }

    public function isOpened($value)
    {
        return $this->builder->where('isOpened', 0);
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
        return $this->builder->where(function ($query) use ($company) {
            $query->where('serial_number', 'like', '%' . $company . '%')
                ->orWhereHas('customer', function ($query) use ($company) {
                    $query->where('company', 'like', '%' . $company . '%')
                        ->orWhere('name', 'like', '%' . $company . '%')
                        ->orWhere('city', 'like', '%' . $company . '%')
                        ->orWhere('ico', 'like', '%' . $company . '%')
                        ->orWhere('postcode', 'like', '%' . $company . '%')
                        ->orWhere('email', 'like', '%' . $company . '%');
                })
                ->orWhereHas('user', function ($query) use ($company) {
                    $query->where('username', 'like', '%' . $company . '%')
                        ->orWhere('firstName', 'like', '%' . $company . '%')
                        ->orWhere('lastName', 'like', '%' . $company . '%')
                        ->orWhere('email', 'like', '%' . $company . '%')
                        ->orWhere('phone', 'like', '%' . $company . '%');
                });
        });
    }

    public function searchByProduct($input)
    {
        return $this->builder->whereHas('orderProducts.product', function ($query) use ($input) {
            $query->where('name', 'like', '%' . $input . '%')
                ->orWhere('description', 'like', '%' . $input . '%');
        });
    }
}
