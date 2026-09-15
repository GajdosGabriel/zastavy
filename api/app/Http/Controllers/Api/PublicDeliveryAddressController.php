<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\OrderRequest;
use App\Models\Order;
use App\Models\User;
use App\Notifications\OrderDeliveryAddressChanged;
use App\Services\Delivery\DeliveryAddressService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Notification;

/**
 * Zmena adresy doručenia z potvrdzovacieho e-mailu, bez prihlásenia.
 *
 * „Objednali sme to na obec, ale doručte to do školy" prišlo doteraz telefonicky
 * a prepisovalo sa ručne. Odkaz v potvrdení to rieši v momente, keď si to
 * zákazník uvedomí — teda hneď po objednávke a pred expedíciou.
 *
 * Prístup stráži `delivery_token`, nie `uuid` objednávky: odkaz na detail sa
 * bežne preposiela kolegom a čítanie objednávky nesmie znamenať právo prepísať,
 * kam sa pošle. Po expedícii je formulár zavretý — balík je na ceste a jeho
 * adresa už je história.
 */
class PublicDeliveryAddressController extends Controller
{
    public function show(Request $request, string $uuid)
    {
        $order = $this->authorizeOrder($request, $uuid);

        return response()->json(['data' => $this->payload($order)]);
    }

    public function update(Request $request, string $uuid)
    {
        $order = $this->authorizeOrder($request, $uuid);

        if (! $order->canEditDelivery()) {
            return response()->json([
                'message' => 'Objednávka je už v expedícii, adresu doručenia meníme telefonicky alebo e-mailom.',
            ], 409);
        }

        $validated = $request->validate(
            OrderRequest::deliveryRules(),
            OrderRequest::deliveryMessages(),
        );

        $service = app(DeliveryAddressService::class);
        $customer = $order->customer;

        $snapshot = $service->resolve(
            $customer,
            $validated['delivery'] ?? null,
            // Adresár patrí zákazníkovi, nie držiteľovi odkazu — verejný
            // formulár preto vypisuje adresu, nevyberá z uložených.
            null,
            $order->billingSnapshot(),
        );

        $before = $order->deliverySnapshot();
        $service->applyToOrder($order, $snapshot, 'zákazník (odkaz z e-mailu)');
        $order->refresh();

        $this->notifyChange($order, $before);

        return response()->json([
            'data' => $this->payload($order),
            'message' => 'Adresu doručenia sme upravili.',
        ]);
    }

    /**
     * Objednávka k tokenu, alebo 404.
     *
     * Neplatný token a neexistujúca objednávka dávajú tú istú odpoveď — inak by
     * sa dalo hádaním zisťovať, ktoré uuid existujú.
     */
    private function authorizeOrder(Request $request, string $uuid): Order
    {
        $order = Order::where('uuid', $uuid)
            ->with(['customer', 'shippingMethod', 'stocks', 'orderProducts'])
            ->first();

        $token = (string) $request->input('token', $request->query('token', ''));

        if (! $order || ! $order->hasValidDeliveryToken($token)) {
            abort(404, 'Odkaz na zmenu adresy je neplatný alebo mu vypršala platnosť.');
        }

        return $order;
    }

    private function payload(Order $order): array
    {
        $customer = $order->billing;

        return [
            'uuid'          => $order->uuid,
            'serial_number' => $order->serial_number,
            'can_edit'      => $order->canEditDelivery(),
            'status'        => $order->shippingStatusLabel(),
            'billing' => [
                'company'  => $customer?->company,
                'street'   => $customer?->street,
                'postcode' => $customer?->postcode,
                'city'     => $customer?->city,
            ],
            'delivery' => $order->deliverySnapshot(),
            'changed_at' => $order->delivery_changed_at?->format('d.m.Y H:i'),
            'changed_by' => $order->delivery_changed_by,
        ];
    }

    /**
     * O zmene sa musí dozvedieť sklad — inak zabalí podľa starej adresy.
     * Zákazník dostane kópiu, aby zmena spravená cez preposlaný odkaz
     * neprešla ticho.
     */
    private function notifyChange(Order $order, array $before): void
    {
        try {
            $notification = new OrderDeliveryAddressChanged($order, $before);

            Notification::send(User::role('super-admin')->get(), $notification);

            if ($order->routeNotificationForMail()) {
                $order->notifyCustomer($notification);
            }
        } catch (\Throwable $e) {
            // Zmena adresy je uložená; zlyhaný e-mail ju nesmie vrátiť späť.
            report($e);
        }
    }
}
