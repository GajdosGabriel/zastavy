<?php

namespace App\Http\Controllers\Api\Dashboard;

use App\Enums\OrderStatus;
use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\OrderEmail;
use App\Models\Shipping;
use App\Notifications\OrderCancelled;
use App\Notifications\OrderCreated;
use App\Notifications\OrderExpedition;
use App\Notifications\OrderPreparing;
use App\Notifications\OrderUpdated;
use Illuminate\Http\Request;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Gate;

/**
 * E-maily zákazníkovi v detaile objednávky: čo všetko mu odišlo a dodatočné
 * odoslanie oznamu, ktorý obsluha pri zmene zabudla poslať (potvrdenie,
 * zmena/storno položiek, storno objednávky, príprava či odoslanie zásielky).
 *
 * Posiela sa vždy aktuálny stav objednávky, nie stav v čase pôvodnej udalosti.
 */
class OrderEmailController extends Controller
{
    public function index(Order $order)
    {
        Gate::authorize('update', $order);

        $emails = $order->emails()->latest('created_at')->latest('id')->get();
        $lastSent = $emails->firstWhere('status', 'sent')?->created_at;
        $lastItemChange = $order->orderProducts()->max('updated_at');

        return response()->json([
            'data' => $emails->map(fn (OrderEmail $email) => [
                'id' => $email->id,
                'type' => $email->type,
                'label' => $email->label(),
                'subject' => $email->subject,
                'recipient' => $email->recipient,
                'status' => $email->status,
                'error' => $email->error,
                'has_body' => $email->body !== null,
                'created_at' => $email->created_at?->format('d.m.Y H:i'),
            ]),
            'recipient' => $order->routeNotificationForMail(),
            // Položky sa menili po poslednom e-maile — zákazník nemusí mať aktuálny stav.
            'changed_since_last_email' => $lastSent && $lastItemChange && $lastSent->lt($lastItemChange),
            'options' => $this->options($order, request()),
            'timeline' => $this->timeline($order, $emails),
        ]);
    }

    /**
     * História objednávky v detaile: čo sa s objednávkou stalo (dodacie listy,
     * vrátenia) a čo o tom zákazník dostal e-mailom — na jednej časovej osi,
     * najnovšie hore.
     */
    private function timeline(Order $order, $emails): array
    {
        $event = fn ($at, string $kind, string $label, ?string $detail = null, string $status = 'ok', array $extra = []) => $at ? [
            'at' => $at->toIso8601String(),
            'at_human' => $at->format('d.m.Y H:i'),
            'kind' => $kind,
            'label' => $label,
            'detail' => $detail,
            'status' => $status,
        ] + $extra : null;
        $items = fn ($rows) => collect($rows)->map(fn ($r) => trim(($r['name'] ?? '').' × '.($r['quantity'] ?? '')))->implode(', ') ?: null;

        $events = [$event($order->created_at, 'order', 'Objednávka prijatá', $order->user?->name ? 'Zadal(a) '.$order->user->name : null)];

        foreach ($order->shippings()->with('stocks.orderProduct')->get() as $shipping) {
            $events[] = $event($shipping->created_at, 'shipping', 'DL #'.$shipping->id.' vystavený', $items($shipping->prepared_items),
                $shipping->dispatched_at ? 'ok' : 'pending');
            $events[] = $event($shipping->dispatched_at, 'shipping', 'DL #'.$shipping->id.' odoslaný',
                $items($shipping->stocks->map(fn ($s) => ['name' => $s->orderProduct?->product_details->name, 'quantity' => $s->quantity])));
        }

        foreach ($order->orderReturns()->get() as $return) {
            $events[] = $event($return->created_at, 'return', 'Vrátenie #'.$return->id.' prijaté');
            $events[] = $event($return->processed_at, 'return', 'Vrátenie #'.$return->id.' spracované');
        }

        foreach ($emails as $email) {
            $events[] = $event($email->created_at, 'email', 'E-mail: '.$email->label(),
                $email->status === 'failed' ? 'Neodoslaný: '.$email->error : $email->recipient,
                $email->status === 'failed' ? 'failed' : 'ok', ['email_id' => $email->body !== null ? $email->id : null]);
        }

        // Pri rovnakom čase je e-mail (dôsledok udalosti) nad udalosťou samotnou.
        return collect($events)->filter()->values()
            ->sortBy([['at', 'desc'], fn ($a, $b) => ($b['kind'] === 'email') <=> ($a['kind'] === 'email')])
            ->values()->all();
    }

    public function show(Order $order, OrderEmail $email)
    {
        Gate::authorize('update', $order);
        abort_unless($email->order_id === $order->id, 404);

        return response()->json(['subject' => $email->subject, 'html' => $email->body]);
    }

    public function preview(Order $order, Request $request)
    {
        [$notification] = $this->resolve($order, $request);
        $mail = $notification->toMail($order);

        return response()->json(['subject' => $mail->subject, 'html' => (string) $mail->render()]);
    }

    public function store(Order $order, Request $request)
    {
        [$notification, $shipping] = $this->resolve($order, $request);
        abort_unless($order->routeNotificationForMail(), 422, 'Objednávka nemá e-mail zákazníka.');

        if ($shipping) {
            // Rovnaký záznam ako pri odoslaní zo zoznamu objednávok — pilulka DL zozelenie.
            $shipping->notices()->create(['notice' => $shipping->dispatched_at ? 'email' : 'preparation_email']);
        }

        $order->notifyCustomer($notification);

        return response()->json(['message' => 'E-mail bol zaradený na odoslanie zákazníkovi.'], 202);
    }

    /** @return array{0: Notification, 1: ?Shipping} */
    private function resolve(Order $order, Request $request): array
    {
        $data = $request->validate([
            'type' => ['required', 'in:confirmation,update,cancelled,shipping'],
            'shipping_id' => ['required_if:type,shipping', 'nullable', 'integer'],
            'note' => ['nullable', 'string', 'max:2000'],
        ]);

        if ($data['type'] === 'shipping') {
            Gate::authorize('ship', $order);
            $shipping = $order->shippings()->findOrFail($data['shipping_id']);
            $order->loadMissing(['customer', 'orderProducts.product', 'orderProducts.stocks', 'shippingMethod', 'paymentMethod']);

            return [$shipping->dispatched_at ? new OrderExpedition($order, $shipping) : new OrderPreparing($order, $shipping), $shipping];
        }

        Gate::authorize('update', $order);
        $order->load(['customer', 'orderProducts.product', 'orderProducts.stocks', 'shippingMethod', 'paymentMethod']);

        return [match ($data['type']) {
            'confirmation' => new OrderCreated($order, resent: true),
            'update' => new OrderUpdated($order, [], $data['note'] ?? null),
            'cancelled' => $order->status === OrderStatus::Cancelled
                ? new OrderCancelled($order)
                : abort(422, 'Objednávka nie je stornovaná.'),
        }, null];
    }

    private function options(Order $order, Request $request): array
    {
        $options = [
            ['type' => 'confirmation', 'label' => 'Potvrdenie objednávky', 'hint' => 'Aktuálne položky, doprava, platba a platobné údaje.'],
            ['type' => 'update', 'label' => 'Zmena objednávky', 'hint' => 'Aktuálny stav po úprave alebo storne položiek, s vlastnou poznámkou.'],
        ];

        if ($order->status === OrderStatus::Cancelled) {
            $options[] = ['type' => 'cancelled', 'label' => 'Storno objednávky', 'hint' => 'Oznámenie, že objednávka bola stornovaná.'];
        }

        if ($request->user()->can('ship', $order)) {
            foreach ($order->shippings()->orderBy('id')->get() as $shipping) {
                $options[] = [
                    'type' => 'shipping',
                    'shipping_id' => $shipping->id,
                    'label' => ($shipping->dispatched_at ? 'Odoslanie zásielky' : 'Príprava zásielky').' – DL #'.$shipping->id,
                    'hint' => $shipping->dispatched_at ? 'Zásielka bola odovzdaná prepravcovi.' : 'Dodací list je vystavený, zásielka čaká na odoslanie.',
                ];
            }
        }

        return $options;
    }
}
