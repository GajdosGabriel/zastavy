<?php

namespace App\Services\Account;

use App\Listeners\SystemLogSubscriber;
use App\Models\Order;

/**
 * Udalosti emailingu z Accountu (`mailing.*`).
 *
 *  - mailing.delivery.sent / failed / bounced – odpoveď vo vlákne objednávky
 *    sa zapíše do histórie e-mailov objednávky (ako predtým z lokálneho
 *    emailingu); bežná ponuka do histórie nepatrí.
 *
 * Odhlásenie (mailing.unsubscribed) eviduje Account, zastavy ho nepotrebujú.
 */
class AccountMailingEvents
{
    /** @param  array<string, mixed>  $data */
    public function handle(string $event, array $data): void
    {
        match ($event) {
            'mailing.delivery.sent', 'mailing.delivery.failed', 'mailing.delivery.bounced' => $this->delivery($event, $data),
            default => null,
        };
    }

    /** @param  array<string, mixed>  $data */
    private function delivery(string $event, array $data): void
    {
        $delivery = (array) ($data['delivery'] ?? []);

        if (($delivery['ref']['type'] ?? null) !== 'order' || ! ($data['campaign']['reply_in_thread'] ?? false)) {
            return;
        }

        $order = Order::where('uuid', $delivery['ref']['id'] ?? '')->first();

        if (! $order) {
            return;
        }

        $sent = $event === 'mailing.delivery.sent';

        SystemLogSubscriber::recordOrderEmail(null, 'Emailing', (string) ($data['subject'] ?? $data['campaign']['name'] ?? ''),
            $delivery['email'] ?? null, $sent ? 'sent' : 'failed',
            error: $sent ? null : (string) ($delivery['error'] ?? ''), body: $sent ? ($data['html'] ?? null) : null, orderId: $order->id);
    }
}
