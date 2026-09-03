<?php

namespace App\Filters;

use App\Enums\ModelStatus;
use App\Models\Order;
use App\Models\Customer;
use Illuminate\Http\Request;
use Illuminate\Database\Eloquent\Builder;

class CustomerFilter extends Filters
{

    protected $filters = ['sortByOrders', 'bySearchInput', 'sortById', 'isMarked', 'withoutOrder', 'status', 'review'];

    /**
     * Zákazníci, na ktorých post-kontrola niečo našla.
     *
     * `?review=open` — má neodbavené nálezy. Presne na túto adresu vedie
     * odkaz zo súhrnu, ktorý chodí adminovi (CustomerReviewDigest).
     * `?review=error` — len tie, kde je chyba, nie drobnosti.
     */
    public function review($value)
    {
        $query = $this->builder->whereHas('review', function ($query) use ($value) {
            $query->open();

            if ($value === 'error') {
                $query->where('score', '<', 60);
            }
        });

        return $query->orderBy('id', 'desc');
    }

    public function status($value)
    {
        $status = ModelStatus::tryFrom((string) $value);

        if (! $status) {
            return $this->builder;
        }

        return $this->builder->where('status', $status->value);
    }

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
