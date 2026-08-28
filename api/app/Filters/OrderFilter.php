<?php

namespace App\Filters;

use App\Enums\OrderStatus;
use Illuminate\Database\Eloquent\Builder;

class OrderFilter extends Filters
{

    protected $filters = ['isActive', 'bySearchInput', 'isOpened', 'isMarked', 'isDeleted', 'searchByProduct', 'isNotificated', 'status', 'shippedAt'];

    /**
     * Objednávky s expedíciou (dodacím listom) vytvorenou v danom období.
     * Hodnoty: dnes | tyzden | mesiac
     */
    public function shippedAt($value)
    {
        $now = now();

        $range = match ((string) $value) {
            'dnes'   => [$now->copy()->startOfDay(), $now->copy()->endOfDay()],
            'tyzden' => [$now->copy()->startOfWeek(), $now->copy()->endOfWeek()],
            'mesiac' => [$now->copy()->startOfMonth(), $now->copy()->endOfMonth()],
            default  => null,
        };

        if (! $range) {
            return $this->builder;
        }

        return $this->builder->whereHas('shippings', function ($query) use ($range) {
            $query->whereBetween('created_at', $range);
        });
    }

    /**
     * Status objednávky je z časti vypočítaný (OrderStatus::fromOrder), preto sa
     * filtruje rovnakou logikou prepísanou do SQL a nie iba stĺpcom orders.status.
     */
    public function status($value)
    {
        $status = OrderStatus::tryFrom((string) $value);

        if (! $status) {
            return $this->builder;
        }

        $ordered  = '(select coalesce(sum(op.quantity), 0) from order_products op where op.order_id = orders.id and op.deleted_at is null)';
        $storno   = '(select coalesce(sum(op.storno), 0) from order_products op where op.order_id = orders.id and op.deleted_at is null)';
        $shipped  = '(select coalesce(sum(st.quantity), 0) from stocks st where st.order_id = orders.id and st.deleted_at is null)';
        $required = "greatest({$ordered} - {$storno}, 0)";

        // Zhodné s Order::isStorned() — teda aj s badge-om v zozname (prázdna objednávka 0 = 0 je stornovaná).
        $isStorned  = "(coalesce(orders.status, '') = 'cancelled' or {$ordered} = {$storno})";
        $isArchived = "coalesce(orders.status, '') = 'archived'";
        // Objednávka, ktorá nie je stornovaná ani archivovaná — až tu sa rozhoduje podľa expedície.
        $isOpen     = "(not {$isStorned} and not {$isArchived})";
        $unshipped  = "({$isOpen} and {$required} <> {$shipped} and {$shipped} = 0)";

        $condition = match ($status) {
            OrderStatus::Cancelled        => $isStorned,
            OrderStatus::Archived         => "(not {$isStorned} and {$isArchived})",
            OrderStatus::Shipped          => "({$isOpen} and {$required} = {$shipped})",
            OrderStatus::PartiallyShipped => "({$isOpen} and {$required} <> {$shipped} and {$shipped} > 0)",
            OrderStatus::ReadyToShip      => "({$unshipped} and coalesce(orders.status, '') = 'ready_to_ship')",
            OrderStatus::Draft            => "({$unshipped} and coalesce(orders.status, '') <> 'ready_to_ship' and coalesce(orders.isOpened, 0) = 0)",
            OrderStatus::Processing       => "({$unshipped} and coalesce(orders.status, '') <> 'ready_to_ship' and coalesce(orders.isOpened, 0) <> 0)",
        };

        return $this->builder->whereRaw($condition);
    }


    public function isActive($value)
    {
        $ordered = '(select coalesce(sum(op.quantity), 0) from order_products op where op.order_id = orders.id and op.deleted_at is null)';
        $storno  = '(select coalesce(sum(op.storno), 0) from order_products op where op.order_id = orders.id and op.deleted_at is null)';

        return $this->builder
            ->whereDoesntHave('stocks')
            ->where(function ($query) {
                $query->whereNull('status')
                    ->orWhere('status', '!=', OrderStatus::Cancelled->value);
            })
            ->whereRaw("{$ordered} > {$storno}");
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
