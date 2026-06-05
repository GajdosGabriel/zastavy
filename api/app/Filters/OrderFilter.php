<?php

namespace App\Filters;

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
        return $this->builder->where(function ($query) use ($company) {
            $query->where('serial_number', 'like', '%' . $company . '%')
                ->orWhereHas('customer', function ($query) use ($company) {
                    $query->where('company', 'like', '%' . $company . '%')
                        ->orWhere('name', 'like', '%' . $company . '%')
                        ->orWhere('city', 'like', '%' . $company . '%')
                        ->orWhere('ico', 'like', '%' . $company . '%')
                        ->orWhere('postcode', 'like', '%' . $company . '%')
                        ->orWhere('email', 'like', '%' . $company . '%');
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
