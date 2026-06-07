<?php

namespace App\Services;

use App\Filters\OrderFilter;
use App\Models\Order;
use App\Models\OrderProduct;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class OrderStatisticsService
{
    private const DASHBOARD_ROLES = ['super-admin', 'admin', 'manager', 'sales', 'warehouse'];

    public function queryFor(User $user, OrderFilter $orderFilters): Builder
    {
        $query = Order::query()
            ->when(! $user->hasAnyRole(self::DASHBOARD_ROLES), function (Builder $query) use ($user) {
                $query->where(function (Builder $query) use ($user) {
                    $query->where('user_id', $user->id);

                    if ($user->customer_id) {
                        $query->orWhere('customer_id', $user->customer_id);
                    }
                });
            });

        $query->filter($orderFilters);

        return $query;
    }

    public function handle(User $user, OrderFilter $orderFilters): array
    {
        $ordersQuery = $this->queryFor($user, $orderFilters);
        $notificationMissingCount = (clone $ordersQuery)
            ->whereHas('shippings')
            ->whereDoesntHave('shippings.notices')
            ->count();
        $orderRows = $this->orderRows(clone $ordersQuery);
        $productRows = $this->productRows(clone $ordersQuery);

        return [
            'orders' => $this->orderSummary($orderRows, $notificationMissingCount),
            'products' => $productRows,
            'undelivered_products' => $productRows
                ->where('remaining_quantity', '>', 0)
                ->values(),
        ];
    }

    public function summary(User $user, OrderFilter $orderFilters): array
    {
        $ordersQuery = $this->queryFor($user, $orderFilters);
        $notificationMissingCount = (clone $ordersQuery)
            ->whereHas('shippings')
            ->whereDoesntHave('shippings.notices')
            ->count();

        return [
            'orders' => $this->orderSummary($this->orderRows(clone $ordersQuery), $notificationMissingCount),
            'products' => [],
            'undelivered_products' => [],
        ];
    }

    public function navigationSummary(User $user, OrderFilter $orderFilters): array
    {
        $ordersQuery = $this->queryFor($user, $orderFilters);
        $unopenedCount = (clone $ordersQuery)->where('isOpened', 0)->count();
        $notificationMissingCount = (clone $ordersQuery)
            ->whereHas('shippings')
            ->whereDoesntHave('shippings.notices')
            ->count();

        return [
            'orders' => [
                'unopened_count' => $unopenedCount,
                'notification_missing_count' => $notificationMissingCount,
                'deleted_count' => 0,
                'isConfirmed' => $unopenedCount,
                'isNotificated' => $notificationMissingCount,
                'isDeleted' => 0,
            ],
            'products' => [],
            'undelivered_products' => [],
        ];
    }

    private function orderRows(Builder $ordersQuery)
    {
        $stockTotals = DB::table('stocks')
            ->select('order_id', DB::raw('SUM(quantity) as shipped_quantity'))
            ->whereNull('deleted_at')
            ->groupBy('order_id');

        return $ordersQuery
            ->leftJoin('order_products', function ($join) {
                $join->on('order_products.order_id', '=', 'orders.id')
                    ->whereNull('order_products.deleted_at');
            })
            ->leftJoinSub($stockTotals, 'stock_totals', function ($join) {
                $join->on('stock_totals.order_id', '=', 'orders.id');
            })
            ->select('orders.id', 'orders.status', 'orders.isOpened', 'orders.deleted_at')
            ->selectRaw('COALESCE(SUM(order_products.quantity), 0) as ordered_quantity')
            ->selectRaw('COALESCE(SUM(order_products.storno), 0) as storno_quantity')
            ->selectRaw('COALESCE(stock_totals.shipped_quantity, 0) as shipped_quantity')
            ->groupBy('orders.id', 'orders.status', 'orders.isOpened', 'orders.deleted_at', 'stock_totals.shipped_quantity')
            ->get()
            ->map(function ($order) {
                $required = max(0, (int) $order->ordered_quantity - (int) $order->storno_quantity);
                $shipped = (int) $order->shipped_quantity;
                $isStorned = $order->status === 'cancelled'
                    || ((int) $order->ordered_quantity > 0
                        && (int) $order->ordered_quantity === (int) $order->storno_quantity);

                return [
                    'id' => $order->id,
                    'is_opened' => (bool) $order->isOpened,
                    'is_deleted' => $order->deleted_at !== null,
                    'ordered_quantity' => (int) $order->ordered_quantity,
                    'storno_quantity' => (int) $order->storno_quantity,
                    'required_quantity' => $required,
                    'shipped_quantity' => $shipped,
                    'remaining_quantity' => max(0, $required - $shipped),
                    'is_finished' => ! $isStorned && $required === $shipped,
                    'is_storned' => $isStorned,
                ];
            });
    }

    private function orderSummary($orderRows, int $notificationMissingCount): array
    {
        $summary = [
            'order_count' => $orderRows->count(),
            'opened_count' => $orderRows->where('is_opened', true)->count(),
            'unopened_count' => $orderRows->where('is_opened', false)->count(),
            'finished_count' => $orderRows->where('is_finished', true)->count(),
            'unfinished_count' => $orderRows->where('remaining_quantity', '>', 0)->count(),
            'notification_missing_count' => $notificationMissingCount,
            'deleted_count' => $orderRows->where('is_deleted', true)->count(),
            'storned_count' => $orderRows->where('is_storned', true)->count(),
            'ordered_quantity' => $orderRows->sum('ordered_quantity'),
            'storno_quantity' => $orderRows->sum('storno_quantity'),
            'required_quantity' => $orderRows->sum('required_quantity'),
            'shipped_quantity' => $orderRows->sum('shipped_quantity'),
            'remaining_quantity' => $orderRows->sum('remaining_quantity'),
        ];

        return $summary + [
            'isConfirmed' => $summary['unopened_count'],
            'isNotificated' => $summary['notification_missing_count'],
            'isDeleted' => $summary['deleted_count'],
        ];
    }

    private function productRows(Builder $ordersQuery)
    {
        $filteredOrders = $ordersQuery->select('orders.id');
        $stockTotals = DB::table('stocks')
            ->select('order_product_id', DB::raw('SUM(quantity) as shipped_quantity'))
            ->whereNull('deleted_at')
            ->groupBy('order_product_id');

        return OrderProduct::query()
            ->joinSub($filteredOrders, 'filtered_orders', function ($join) {
                $join->on('filtered_orders.id', '=', 'order_products.order_id');
            })
            ->leftJoin('products', 'products.id', '=', 'order_products.product_id')
            ->leftJoinSub($stockTotals, 'stock_totals', function ($join) {
                $join->on('stock_totals.order_product_id', '=', 'order_products.id');
            })
            ->whereNull('order_products.deleted_at')
            ->select('order_products.product_id', 'products.name', 'products.unit_value')
            ->selectRaw('COUNT(DISTINCT order_products.order_id) as order_count')
            ->selectRaw('SUM(order_products.quantity) as ordered_quantity')
            ->selectRaw('SUM(order_products.storno) as storno_quantity')
            ->selectRaw('SUM(COALESCE(stock_totals.shipped_quantity, 0)) as shipped_quantity')
            ->selectRaw('SUM(CASE WHEN order_products.quantity - order_products.storno > 0 THEN order_products.quantity - order_products.storno ELSE 0 END) as required_quantity')
            ->selectRaw('SUM(CASE WHEN order_products.quantity - order_products.storno - COALESCE(stock_totals.shipped_quantity, 0) > 0 THEN order_products.quantity - order_products.storno - COALESCE(stock_totals.shipped_quantity, 0) ELSE 0 END) as remaining_quantity')
            ->selectRaw('SUM(order_products.total) as total')
            ->groupBy('order_products.product_id', 'products.name', 'products.unit_value')
            ->orderByDesc('remaining_quantity')
            ->orderBy('products.name')
            ->get()
            ->map(fn ($product) => [
                'product_id' => $product->product_id,
                'name' => $product->name ?? 'Neznamy tovar',
                'unit_value' => $product->unit_value ?? 'ks',
                'order_count' => (int) $product->order_count,
                'ordered_quantity' => (int) $product->ordered_quantity,
                'storno_quantity' => (int) $product->storno_quantity,
                'required_quantity' => (int) $product->required_quantity,
                'shipped_quantity' => (int) $product->shipped_quantity,
                'remaining_quantity' => (int) $product->remaining_quantity,
                'total' => (float) $product->total,
            ]);
    }
}
