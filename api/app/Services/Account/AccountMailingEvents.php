<?php

namespace App\Services\Account;

use App\Listeners\SystemLogSubscriber;
use App\Models\Order;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Udalosti emailingu z Accountu (`mailing.*`).
 *
 *  - mailing.delivery.sent / failed / bounced – odpoveď vo vlákne objednávky
 *    sa zapíše do histórie e-mailov objednávky (ako predtým z lokálneho
 *    emailingu); bežná ponuka do histórie nepatrí,
 *  - mailing.unsubscribed – kým existuje lokálny adresár, odhlásenie sa
 *    zapíše aj doňho, aby sa nerozišli.
 */
class AccountMailingEvents
{
    /** @param  array<string, mixed>  $data */
    public function handle(string $event, array $data): void
    {
        match ($event) {
            'mailing.delivery.sent', 'mailing.delivery.failed', 'mailing.delivery.bounced' => $this->delivery($event, $data),
            'mailing.unsubscribed' => $this->unsubscribed($data),
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

    /** @param  array<string, mixed>  $data */
    private function unsubscribed(array $data): void
    {
        $email = mb_strtolower(trim((string) ($data['contact']['email'] ?? '')));

        if ($email === '' || ! Schema::hasTable('mailing_contacts')) {
            return;
        }

        DB::table('mailing_contacts')->where('email', $email)->whereNull('unsubscribed_at')
            ->update(['unsubscribed_at' => now(), 'updated_at' => now()]);
    }
}
