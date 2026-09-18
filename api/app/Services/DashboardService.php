<?php

namespace App\Services;

use App\Enums\OrderStatus;
use App\Filters\OrderFilter;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Prehľad pre úvodnú stránku administrácie.
 *
 * Všetky čísla sú obmedzené rovnakým rozsahom viditeľnosti ako zoznam
 * objednávok (OrderStatisticsService::queryFor), takže portálový používateľ
 * vidí len svoju organizáciu. Dlaždice „fronty" počítajú presne tie isté
 * filtre, ktoré sa po kliknutí použijú v zozname objednávok.
 */
class DashboardService
{
    private const ORDERED  = '(select coalesce(sum(op.quantity), 0) from order_products op where op.order_id = orders.id and op.deleted_at is null)';
    private const STORNO   = '(select coalesce(sum(op.storno), 0) from order_products op where op.order_id = orders.id and op.deleted_at is null)';
    private const SHIPPED  = '(select coalesce(sum(st.quantity), 0) from stocks st where st.order_id = orders.id and st.deleted_at is null)';

    /** Hodnota tovaru po odrátaní storien — podiel z `total`, aby sedeli prípadné zľavy na riadku. */
    private const NET_VALUE = 'case when order_products.quantity > 0 then order_products.total * greatest(order_products.quantity - order_products.storno, 0) / order_products.quantity else 0 end';

    public function __construct(private OrderStatisticsService $statistics)
    {
    }

    public function handle(User $user): array
    {
        return [
            'generated_at'     => now()->toIso8601String(),
            'queue'            => $this->queue($user),
            'sales'            => $this->sales($user),
            'daily'            => $this->daily($user, 30),
            'waiting'          => $this->waiting($user, 7),
            'missing_products' => $this->missingProducts($user, 8),
            'top_products'     => $this->topProducts($user, 5),
        ];
    }

    private function base(User $user, array $filters = []): Builder
    {
        return $this->statistics->queryFor($user, new OrderFilter(new Request($filters)));
    }

    /** Objednávka, ktorá nie je stornovaná ani archivovaná a ešte z nej niečo treba vyexpedovať. */
    private function open(Builder $query): Builder
    {
        $required = 'greatest('.self::ORDERED.' - '.self::STORNO.', 0)';

        return $query
            ->where(fn ($q) => $q->whereNull('orders.status')
                ->orWhereNotIn('orders.status', [OrderStatus::Cancelled->value, OrderStatus::Archived->value]))
            ->whereRaw(self::ORDERED.' > '.self::STORNO)
            ->whereRaw("{$required} > ".self::SHIPPED);
    }

    private function queue(User $user): array
    {
        $count = fn (array $filters) => $this->base($user, $filters)->count();

        return [
            'unopened'          => $count(['isOpened' => true]),
            'active'            => $count(['isActive' => true]),
            'ready_to_ship'     => $count(['status' => OrderStatus::ReadyToShip->value]),
            'partially_shipped' => $count(['status' => OrderStatus::PartiallyShipped->value]),
            'not_notified'      => $count(['isNotificated' => true]),
            'marked'            => $count(['isMarked' => true]),
            'open'              => $this->open($this->base($user))->count(),
            'overdue'           => $this->open($this->base($user))
                ->where('orders.created_at', '<', now()->subDays(7)->startOfDay())
                ->count(),
        ];
    }

    private function sales(User $user): array
    {
        $now = now();

        $periods = [
            'today' => [
                [$now->copy()->startOfDay(), $now->copy()],
                [$now->copy()->subDay()->startOfDay(), $now->copy()->subDay()],
            ],
            'week' => [
                [$now->copy()->startOfWeek(), $now->copy()],
                [$now->copy()->subWeek()->startOfWeek(), $now->copy()->subWeek()],
            ],
            'month' => [
                [$now->copy()->startOfMonth(), $now->copy()],
                // Rovnako dlhý úsek minulého mesiaca (1. – dnešný deň), nie celý mesiac.
                [$now->copy()->subMonthNoOverflow()->startOfMonth(), $now->copy()->subMonthNoOverflow()],
            ],
        ];

        return collect($periods)->map(function (array $ranges) use ($user) {
            [$current, $previous] = $ranges;

            return [
                'current'  => $this->ordersInRange($user, ...$current),
                'previous' => $this->ordersInRange($user, ...$previous),
                'shipped'  => $this->shippedInRange($user, ...$current),
            ];
        })->all();
    }

    private function ordersInRange(User $user, CarbonInterface $from, CarbonInterface $to): array
    {
        $orderIds = $this->base($user)
            ->where(fn ($q) => $q->whereNull('orders.status')->orWhere('orders.status', '!=', OrderStatus::Cancelled->value))
            ->whereBetween('orders.created_at', [$from, $to])
            ->select('orders.id');

        $row = DB::table('order_products')
            ->joinSub($orderIds, 'o', 'o.id', '=', 'order_products.order_id')
            ->whereNull('order_products.deleted_at')
            ->selectRaw('count(distinct order_products.order_id) as order_count')
            ->selectRaw('coalesce(sum('.self::NET_VALUE.'), 0) as value')
            ->first();

        return [
            'order_count' => (int) $row->order_count,
            'value'       => round((float) $row->value, 2),
        ];
    }

    private function shippedInRange(User $user, CarbonInterface $from, CarbonInterface $to): array
    {
        $row = DB::table('stocks')
            ->join('shippings', 'shippings.id', '=', 'stocks.shipping_id')
            ->join('order_products', 'order_products.id', '=', 'stocks.order_product_id')
            ->joinSub($this->base($user)->select('orders.id'), 'o', 'o.id', '=', 'stocks.order_id')
            ->whereNull('stocks.deleted_at')
            ->whereBetween('shippings.created_at', [$from, $to])
            ->selectRaw('count(distinct stocks.order_id) as order_count')
            ->selectRaw('coalesce(sum(stocks.quantity * coalesce(order_products.price, 0)), 0) as value')
            ->first();

        return [
            'order_count' => (int) $row->order_count,
            'value'       => round((float) $row->value, 2),
        ];
    }

    /** Denný priebeh za posledných N dní — nové objednávky a expedovaná hodnota. */
    private function daily(User $user, int $days): array
    {
        $from = now()->subDays($days - 1)->startOfDay();

        $orders = DB::table('order_products')
            ->joinSub(
                $this->base($user)
                    ->where(fn ($q) => $q->whereNull('orders.status')->orWhere('orders.status', '!=', OrderStatus::Cancelled->value))
                    ->where('orders.created_at', '>=', $from)
                    ->select('orders.id', 'orders.created_at'),
                'o', 'o.id', '=', 'order_products.order_id'
            )
            ->whereNull('order_products.deleted_at')
            ->selectRaw('date(o.created_at) as day')
            ->selectRaw('count(distinct o.id) as order_count')
            ->selectRaw('coalesce(sum('.self::NET_VALUE.'), 0) as value')
            ->groupByRaw('date(o.created_at)')
            ->get()
            ->keyBy('day');

        $shipped = DB::table('stocks')
            ->join('shippings', 'shippings.id', '=', 'stocks.shipping_id')
            ->join('order_products', 'order_products.id', '=', 'stocks.order_product_id')
            ->joinSub($this->base($user)->select('orders.id'), 'o', 'o.id', '=', 'stocks.order_id')
            ->whereNull('stocks.deleted_at')
            ->where('shippings.created_at', '>=', $from)
            ->selectRaw('date(shippings.created_at) as day')
            ->selectRaw('coalesce(sum(stocks.quantity * coalesce(order_products.price, 0)), 0) as value')
            ->groupByRaw('date(shippings.created_at)')
            ->get()
            ->keyBy('day');

        return collect(range(0, $days - 1))->map(function (int $offset) use ($from, $orders, $shipped) {
            $day = $from->copy()->addDays($offset)->toDateString();

            return [
                'date'          => $day,
                'order_count'   => (int) ($orders[$day]->order_count ?? 0),
                'value'         => round((float) ($orders[$day]->value ?? 0), 2),
                'shipped_value' => round((float) ($shipped[$day]->value ?? 0), 2),
            ];
        })->all();
    }

    /** Najdlhšie čakajúce otvorené objednávky — kandidáti, ktorým sa treba venovať ako prvým. */
    private function waiting(User $user, int $limit): array
    {
        $required = 'greatest('.self::ORDERED.' - '.self::STORNO.', 0)';

        return $this->open($this->base($user))
            ->with('customer:id,company,city')
            ->select('orders.id', 'orders.serial_number', 'orders.customer_id', 'orders.created_at', 'orders.status', 'orders.isOpened')
            ->selectRaw("{$required} as required_quantity")
            ->selectRaw(self::SHIPPED.' as shipped_quantity')
            ->orderBy('orders.created_at')
            ->limit($limit)
            ->get()
            ->map(fn ($order) => [
                'id'                 => $order->id,
                'serial_number'      => $order->serial_number,
                'customer'           => $order->customer?->company,
                'city'               => $order->customer?->city,
                'created_at'         => $order->created_at?->toIso8601String(),
                'days_waiting'       => (int) $order->created_at?->startOfDay()->diffInDays(now()->startOfDay()),
                'is_opened'          => (bool) $order->isOpened,
                'required_quantity'  => (int) $order->required_quantity,
                'shipped_quantity'   => (int) $order->shipped_quantity,
            ])
            ->all();
    }

    /** Tovar, ktorý treba ešte dodať do otvorených objednávok (čo vyrobiť / objednať). */
    private function missingProducts(User $user, int $limit): array
    {
        $stockTotals = DB::table('stocks')
            ->select('order_product_id', DB::raw('sum(quantity) as shipped_quantity'))
            ->whereNull('deleted_at')
            ->groupBy('order_product_id');

        $remaining = 'greatest(order_products.quantity - order_products.storno - coalesce(stock_totals.shipped_quantity, 0), 0)';

        return DB::table('order_products')
            ->joinSub($this->open($this->base($user))->select('orders.id'), 'o', 'o.id', '=', 'order_products.order_id')
            ->leftJoin('products', 'products.id', '=', 'order_products.product_id')
            ->leftJoinSub($stockTotals, 'stock_totals', 'stock_totals.order_product_id', '=', 'order_products.id')
            ->whereNull('order_products.deleted_at')
            ->whereRaw("{$remaining} > 0")
            ->select('order_products.product_id', 'products.name', 'products.unit_value')
            ->selectRaw("sum({$remaining}) as remaining_quantity")
            ->selectRaw('count(distinct order_products.order_id) as order_count')
            ->groupBy('order_products.product_id', 'products.name', 'products.unit_value')
            ->orderByDesc('remaining_quantity')
            ->limit($limit)
            ->get()
            ->map(fn ($row) => [
                'product_id'         => $row->product_id,
                'name'               => $row->name ?? 'Neznámy tovar',
                'unit_value'         => $row->unit_value ?? 'ks',
                'remaining_quantity' => (int) $row->remaining_quantity,
                'order_count'        => (int) $row->order_count,
            ])
            ->all();
    }

    /** Najpredávanejší tovar tohto mesiaca podľa hodnoty. */
    private function topProducts(User $user, int $limit): array
    {
        $orderIds = $this->base($user)
            ->where(fn ($q) => $q->whereNull('orders.status')->orWhere('orders.status', '!=', OrderStatus::Cancelled->value))
            ->where('orders.created_at', '>=', now()->startOfMonth())
            ->select('orders.id');

        return DB::table('order_products')
            ->joinSub($orderIds, 'o', 'o.id', '=', 'order_products.order_id')
            ->leftJoin('products', 'products.id', '=', 'order_products.product_id')
            ->whereNull('order_products.deleted_at')
            ->select('order_products.product_id', 'products.name', 'products.unit_value')
            ->selectRaw('sum(greatest(order_products.quantity - order_products.storno, 0)) as quantity')
            ->selectRaw('coalesce(sum('.self::NET_VALUE.'), 0) as value')
            ->groupBy('order_products.product_id', 'products.name', 'products.unit_value')
            ->havingRaw('sum(greatest(order_products.quantity - order_products.storno, 0)) > 0')
            ->orderByDesc('value')
            ->limit($limit)
            ->get()
            ->map(fn ($row) => [
                'product_id' => $row->product_id,
                'name'       => $row->name ?? 'Neznámy tovar',
                'unit_value' => $row->unit_value ?? 'ks',
                'quantity'   => (int) $row->quantity,
                'value'      => round((float) $row->value, 2),
            ])
            ->all();
    }
}
