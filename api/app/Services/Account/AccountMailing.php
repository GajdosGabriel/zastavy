<?php

namespace App\Services\Account;

use App\Enums\MailingTrigger;
use App\Jobs\ReportMailingEvent;
use App\Models\Order;
use App\Models\Shipping;
use App\Support\OrderThread;
use Carbon\Carbon;

/**
 * Emailing (kampane a automatizácie) beží v Accounte – zastavy len hlásia
 * udalosti objednávok s premennými do textu.
 *
 * Kľúč udalosti je `order:{uuid}` (pri zásielke `order:{uuid}:shipping:{id}`),
 * takže opakované hlásenie nič nezdvojí. Referencia je tá istá ako pri
 * e-mailoch objednávky (OrderMailer::ref) – odpoveď vo vlákne v Accounte tak
 * nájde potvrdenie objednávky a zacituje ho.
 */
class AccountMailing
{
    public function event(MailingTrigger $event, Order $order, ?Shipping $shipping = null, ?Carbon $at = null): void
    {
        $email = mb_strtolower(trim($order->routeNotificationForMail() ?? ''));

        if ($email === '') {
            return;
        }

        ReportMailingEvent::dispatch([
            'event' => $event->value,
            'email' => $email,
            'name' => $order->billing->name ?? null,
            'ref' => OrderMailer::ref($order),
            'dedupe_key' => self::dedupeKey($order, $shipping),
            'occurred_at' => ($at ?? now())->toIso8601String(),
            'variables' => [
                'cislo_objednavky' => $order->serial_number,
                'firma' => $order->billing->company ?: ($order->billing->name ?? null),
                'odkaz_objednavky' => $order->publicUrl(),
            ],
            'thread_id' => OrderThread::id($order),
        ], cancel: $event === MailingTrigger::OrderCancelled ? $order->uuid : null)->afterCommit();
    }

    /** Objednávka stornovaná alebo zmazaná: čakajúce maily k nej sa neodošlú. */
    public function cancel(Order $order): void
    {
        ReportMailingEvent::dispatch(null, $order->uuid)->afterCommit();
    }

    /** Udalosti pre výber spúšťača v Accounte. */
    public function triggers(): array
    {
        return array_map(fn (MailingTrigger $trigger) => [
            'key' => $trigger->value,
            'label' => $trigger->label(),
            'short_label' => $trigger->shortLabel(),
            'variables' => MailingTrigger::VARIABLES,
            'quote_kind' => $trigger->quoteKind(),
        ], MailingTrigger::cases());
    }

    public static function dedupeKey(Order $order, ?Shipping $shipping = null): string
    {
        return 'order:'.$order->uuid.($shipping ? ':shipping:'.$shipping->id : '');
    }
}
