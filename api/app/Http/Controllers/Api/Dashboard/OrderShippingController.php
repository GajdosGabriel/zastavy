<?php

namespace App\Http\Controllers\Api\Dashboard;

use App\Actions\IssueCouponForOrder;
use App\Enums\OrderStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\OrderResource;
use App\Http\Resources\ShippingResource;
use App\Models\Order;
use App\Models\Shipping;
use App\Notifications\OrderExpedition;
use App\Notifications\OrderPreparing;
use App\Services\ShippingService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class OrderShippingController extends Controller
{
    private function selection(Order $order, Request $request): array
    {
        $data = $request->validate([
            'idempotency_key' => ['sometimes', 'uuid'],
            'notify_customer' => ['sometimes', 'boolean'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.order_product_id' => ['required', 'integer', 'distinct'],
            'items.*.quantity' => ['required', 'integer', 'min:0'],
        ]);
        $rows = [];
        foreach ($data['items'] as $row) {
            $item = $order->orderProducts->firstWhere('id', $row['order_product_id']);
            abort_unless($item, 422, 'Položka nepatrí k objednávke.');
            abort_if($row['quantity'] > max(0, $item->quantity - $item->storno - $item->stockSum), 422, 'Množstvo presahuje zostávajúce položky.');
            if ($row['quantity'] > 0) {
                $rows[] = ['order_product_id' => $item->id, 'quantity' => (int) $row['quantity'], 'name' => $item->product_details->name, 'unit' => $item->product_details->unit_value];
            }
        }
        abort_if(! $rows, 422, 'Vyberte aspoň jednu položku.');

        return $rows;
    }

    public function preview(Order $order, Request $request)
    {
        Gate::authorize('ship', $order);
        if ($request->filled('shipping_id')) {
            $shipping = $order->shippings()->findOrFail($request->integer('shipping_id'));

            return response()->json(['subject' => 'Potvrdenie odoslania objednávky č. '.$order->serial_number,
                'html' => view('emails.orderDispatched', compact('order', 'shipping'))->render()]);
        }
        $shipping = new Shipping(['prepared_items' => $this->selection($order, $request)]);

        return response()->json(['subject' => 'Vašu objednávku pripravujeme v sklade – č. '.$order->serial_number,
            'html' => view('emails.orderPreparing', compact('order', 'shipping'))->render()]);
    }

    public function store(Order $order, Request $request)
    {
        Gate::authorize('ship', $order);
        $shipping = DB::transaction(function () use ($order, $request) {
            $order = Order::whereKey($order->id)->lockForUpdate()->firstOrFail();
            $request->validate(['idempotency_key' => ['sometimes', 'uuid']]);
            $hash = hash('sha256', json_encode($request->input('items')));
            if ($request->filled('idempotency_key') && ($existing = $order->shippings()->where('submission_key', $request->idempotency_key)->first())) {
                abort_unless(hash_equals($existing->submission_hash, $hash), 409, 'Identifikátor už patrí inému dodaciemu listu.');

                return $existing;
            }
            abort_if(in_array($order->status, [OrderStatus::Cancelled, OrderStatus::Archived]), 422, 'Túto objednávku nemožno pripravovať.');
            abort_if($order->shippings()->whereNull('dispatched_at')->exists(), 409, 'Najprv odošlite alebo zrušte rozpracovaný dodací list.');
            $rows = $this->selection($order, $request);
            $shipping = $order->shippings()->create(['prepared_items' => $rows, 'submission_key' => $request->idempotency_key, 'submission_hash' => $hash]);
            $order->update(['isOpened' => 1, 'status' => OrderStatus::Processing]);
            if ($request->boolean('notify_customer', true) && $order->routeNotificationForMail()) {
                $order->notifyCustomer(new OrderPreparing($order, $shipping));
                $shipping->notices()->create(['notice' => 'preparation_email']);
            }

            return $shipping;
        });

        return $this->result($order, $shipping);
    }

    public function update(Order $order, Shipping $shipping, Request $request)
    {
        Gate::authorize('ship', $order);
        abort_unless($shipping->order_id === $order->id, 404);
        $request->validate(['notify_customer' => ['sometimes', 'boolean']]);
        DB::transaction(function () use ($order, $shipping, $request) {
            $order = Order::whereKey($order->id)->lockForUpdate()->firstOrFail();
            $shipping = $order->shippings()->whereKey($shipping->id)->lockForUpdate()->firstOrFail();
            if ($shipping->dispatched_at) {
                return;
            }
            abort_if(in_array($order->status, [OrderStatus::Cancelled, OrderStatus::Archived]), 422, 'Objednávku nemožno odoslať.');
            foreach ($shipping->prepared_items as $row) {
                $item = $order->orderProducts->firstWhere('id', $row['order_product_id']);
                abort_if(! $item || $row['quantity'] > max(0, $item->quantity - $item->storno - $item->stockSum), 422, 'Položky sa zmenili. Zrušte prípravu a vytvorte nový dodací list.');
            }
            (new ShippingService)->create($order, $shipping->prepared_items, null, $shipping);
            $order->refresh();
            if ($request->boolean('notify_customer', true) && $order->routeNotificationForMail()) {
                $order->notifyCustomer(new OrderExpedition($order, $shipping->refresh()));
                $shipping->notices()->create(['notice' => 'email']);
            }
            if ($order->isFinished()) {
                (new IssueCouponForOrder)->handle($order);
            }
        });

        return $this->result($order, $shipping->refresh());
    }

    public function destroy(Order $order, Shipping $shipping)
    {
        Gate::authorize('ship', $order);
        abort_unless($shipping->order_id === $order->id, 404);
        DB::transaction(function () use ($order, $shipping) {
            Order::whereKey($order->id)->lockForUpdate()->firstOrFail();
            $shipping->refresh();
            abort_if($shipping->dispatched_at, 422, 'Odoslaný dodací list nemožno zrušiť.');
            $shipping->notices()->delete();
            $shipping->delete();
        });

        return response()->noContent();
    }

    private function result(Order $order, Shipping $shipping)
    {
        return (new ShippingResource($shipping->load('notices', 'stocks')))->additional([
            'order' => new OrderResource($order->refresh()->load(['customer.users', 'user', 'shippings.notices', 'orderProducts', 'stocks'])),
        ]);
    }
}
