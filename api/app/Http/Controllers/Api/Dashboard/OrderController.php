<?php

namespace App\Http\Controllers\Api\Dashboard;

use App\Enums\ModelStatus;
use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\Stock;
use App\Filters\OrderFilter;
use App\Http\Requests\OrderRequest;
use App\Http\Controllers\Controller;
use App\Http\Resources\OrderIndexResource;
use App\Http\Resources\OrderResource;
use App\Http\Resources\OrderStatisticResource;
use App\Services\CustomerService;
use App\Services\Delivery\DeliveryAddressService;
use App\Services\OrderStatisticsService;
use App\Actions\IssueCouponForOrder;
use App\Actions\StoreOrder;
use App\Models\PaymentMethod;
use App\Models\ShippingMethod;
use App\Notifications\OrderUpdated;
use App\Notifications\OrderCancelled;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class OrderController extends Controller
{
    public function index(OrderFilter $orderFilters)
    {
        Gate::authorize('viewAny', Order::class);

        $orders = app(OrderStatisticsService::class)
            ->queryFor(request()->user(), $orderFilters)
            ->with(['customer', 'shippings.stocks', 'shippings.notices', 'orderProducts', 'stocks', 'mark'])
            ->orderBy('created_at', 'desc')
            ->paginate();

        // Zoznam statusov ide v meta, aby ho filter v zozname vedel vyrenderovať.
        return OrderIndexResource::collection($orders)->additional([
            'meta' => [
                'statuses' => array_map(fn (OrderStatus $status) => $status->toArray(), OrderStatus::cases()),
            ],
        ]);
    }

    public function statistics(OrderFilter $orderFilters, OrderStatisticsService $statistics)
    {
        Gate::authorize('viewAny', Order::class);

        return new OrderStatisticResource(
            $statistics->handle(request()->user(), $orderFilters)
        );
    }

    public function show(Order $order)
    {
        Gate::authorize('view', $order);

        return response(new OrderResource($order->load(['customer.users', 'user', 'shippingMethod', 'paymentMethod', 'attachments'])));
    }

    public function update(Order $order, OrderRequest $request)
    {
        if ($request->makeStorned) {
            Gate::authorize('storno', $order);

            DB::transaction(function () use ($order) {
                $order = Order::whereKey($order->id)->lockForUpdate()->firstOrFail();
                Gate::authorize('storno', $order);
                $order->forceFill(['status' => OrderStatus::Cancelled])->save();

                foreach ($order->orderProducts as $product) {
                    $shippedQuantity = Stock::where('order_product_id', '=', $product->id)->sum('quantity');
                    $stornoQuantity = max(0, $product->quantity - $shippedQuantity);
                    $remainingQuantity = max(0, $product->quantity - $product->storno - $shippedQuantity);

                    $product->update([
                        'storno' => $stornoQuantity,
                        'status' => $remainingQuantity > 0 ? ModelStatus::Cancelled : $product->status,
                    ]);
                }
            });

            if ($request->boolean('notify_customer', false)) {
                $order->loadMissing(['customer', 'orderProducts.product', 'orderProducts.stocks']);
                $order->notifyCustomer(new OrderCancelled($order));
            }

            return new OrderResource($order->refresh()->load(['customer.users', 'user']));
        }

        Gate::authorize('update', $order);

        $changes = $this->detectChanges($order, $request);

        $previousStatus = $order->status;

        DB::transaction(function () use ($order, $request) {
            $order = Order::whereKey($order->id)->lockForUpdate()->firstOrFail();
            $changes = $request->safe()->only(['shipping_method_id', 'payment_method_id', 'status', 'isOpened', 'note', 'wants_coupon']);
            if (array_key_exists('shipping_method_id', $changes)) {
                $method = ShippingMethod::where('active', true)->findOrFail($changes['shipping_method_id']);
                $changes['shipping_price'] = $method->resolvePrice($order->priceSum());
                $changes['shipping_method_name'] = $method->name;
            }
            if (array_key_exists('payment_method_id', $changes)) {
                $method = $changes['payment_method_id'] ? PaymentMethod::where('active', true)->findOrFail($changes['payment_method_id']) : null;
                $changes['payment_fee'] = $method?->fee ?? 0;
                $changes['payment_method_name'] = $method?->name;
            }
            if (array_key_exists('wants_coupon', $changes) && $order->coupon_id) {
                $changes['wants_coupon'] = false;
            }
            $this->updateDeliveryAddress($order, $request);
            $order->update($changes);
        });

        $order->refresh();
        $newStatus = $order->status;

        if ($previousStatus !== OrderStatus::Archived && $newStatus === OrderStatus::Archived) {
            $order->loadMissing(['customer', 'orderProducts']);
            (new IssueCouponForOrder)->handle($order);
        }

        if ($request->boolean('notify_customer') && !empty($changes)) {
            $order->notifyCustomer(new OrderUpdated($order->refresh()->load('orderProducts.product', 'customer'), $changes));
        }

        return new OrderResource($order);
    }

    /**
     * Obsluha prepisuje adresu doručenia z detailu objednávky.
     *
     * Kľúč `delivery` musí v requeste chýbať, ak sa adresa nemá dotknúť —
     * poslané prázdne pole znamená „doručiť na sídlo" a odtlačok sa zmaže.
     * Zaškrtnuté `delivery.save_address` adresu zároveň pridá do adresára
     * zákazníka na budúce objednávky.
     */
    private function updateDeliveryAddress(Order $order, OrderRequest $request): void
    {
        if (! $request->has('delivery') && ! $request->has('customer_address_id')) {
            return;
        }

        abort_unless($order->canEditDelivery(), 409, 'Doručenie vybavenej objednávky už nemožno meniť.');
        $customer = $order->customer;

        if (! $customer) {
            return;
        }

        $service = app(DeliveryAddressService::class);

        $snapshot = $service->resolve(
            $customer,
            $request->input('delivery'),
            $request->input('customer_address_id') ? (int) $request->input('customer_address_id') : null,
            $order->billingSnapshot(),
        );

        if ($request->boolean('delivery.save_address') && $snapshot['customer_address_id'] === null && filled($snapshot['delivery_street'])) {
            $snapshot['customer_address_id'] = $service
                ->remember($customer, $snapshot, $request->input('delivery.label'))?->id;
        }

        $service->applyToOrder($order, $snapshot, $request->user()?->username ?: 'obsluha');
    }

    private function detectChanges(Order $order, OrderRequest $request): array
    {
        $changes = [];

        if ($request->has('delivery') || $request->has('customer_address_id')) {
            $before = $order->deliverySnapshot();
            $after = app(DeliveryAddressService::class)->resolve(
                $order->customer,
                $request->input('delivery'),
                $request->input('customer_address_id') ? (int) $request->input('customer_address_id') : null,
            $order->billingSnapshot(),
            );

            $oldLine = $this->addressLine($before);
            $newLine = $this->addressLine([
                'company'  => $after['delivery_company'] ?: $order->customer?->company,
                'name'     => $after['delivery_name'],
                'street'   => $after['delivery_street'] ?: $order->customer?->street,
                'postcode' => $after['delivery_postcode'] ?: $order->customer?->postcode,
                'city'     => $after['delivery_city'] ?: $order->customer?->city,
            ]);

            if ($oldLine !== $newLine) {
                $changes[] = ['label' => 'Adresa doručenia', 'old' => $oldLine ?: '—', 'new' => $newLine ?: '—'];
            }
        }

        if ($request->filled('shipping_method_id') && $request->shipping_method_id != $order->shipping_method_id) {
            $oldMethod = $order->shippingMethod?->name ?? '—';
            $newMethod = ShippingMethod::find($request->shipping_method_id)?->name ?? '—';
            $changes[] = ['label' => 'Spôsob dopravy', 'old' => $oldMethod, 'new' => $newMethod];
        }

        if ($request->filled('payment_method_id') && $request->payment_method_id != $order->payment_method_id) {
            $oldMethod = $order->paymentMethod?->name ?? '—';
            $newMethod = PaymentMethod::find($request->payment_method_id)?->name ?? '—';
            $changes[] = ['label' => 'Spôsob platby', 'old' => $oldMethod, 'new' => $newMethod];
        }

        if ($request->has('note') && ($request->note ?? '') !== ($order->note ?? '')) {
            $changes[] = ['label' => 'Poznámka', 'old' => $order->note ?? '—', 'new' => $request->note ?? '—'];
        }

        if ($request->has('wants_coupon') && ! $order->coupon_id && $request->boolean('wants_coupon') !== (bool) $order->wants_coupon) {
            $changes[] = [
                'label' => 'Zľavový kupón',
                'old'   => $order->wants_coupon ? 'Áno' : 'Nie',
                'new'   => $request->boolean('wants_coupon') ? 'Áno' : 'Nie',
            ];
        }

        if ($request->boolean('has_product_changes')) {
            $changes[] = ['label' => 'Položky objednávky', 'old' => '—', 'new' => 'Pridané nové položky'];
        }

        return $changes;
    }

    /** Adresa v jednom riadku — na porovnanie „pred / po" v e-maile o zmene. */
    private function addressLine(array $address): string
    {
        return collect([
            $address['company'] ?? null,
            $address['name'] ?? null,
            $address['street'] ?? null,
            trim(($address['postcode'] ?? '').' '.($address['city'] ?? '')),
        ])->filter()->implode(', ');
    }

    public function store(\App\Http\Requests\CreateOrderRequest $request)
    {
        Gate::authorize('create', Order::class);

        $order = app(\App\Services\CreateOrderService::class)->handle($request);

        return new OrderResource($order->refresh()->load(['customer.users', 'user', 'orderProducts']));
    }

    public function destroy(Order $order)
    {
        Gate::authorize('delete', $order);

        DB::transaction(function () use ($order) {
            $order = Order::whereKey($order->id)->lockForUpdate()->firstOrFail();
            Gate::authorize('delete', $order);
            abort_if($order->stocks()->exists(), 422, 'Objednávku so skladovou históriou nemožno zmazať.');
            $order->orderProducts()->delete();
            $order->delete();
        });

        return response(new OrderResource($order));
    }
}
