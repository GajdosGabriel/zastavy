<?php

namespace App\Services;

use App\Models\Order;
use App\Models\OrderMessage;
use App\Models\SalesQuote;
use App\Models\SalesQuoteMessage;
use App\Models\User;
use App\Notifications\QuoteMessageReceived;
use App\Notifications\StaffAlert;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;

/**
 * Zápis do histórie upozornení obsluhy (zvonček → História).
 *
 * Príjemcov vyberá rovnako, ako počítadlá v DashboardService::notifications:
 * objednávky vidí super-admin a obsluha, ktorej objednávka patrí; ponuky
 * obsluha s prístupom k predaju (SalesAccess::staff).
 *
 * Zlyhanie zápisu nesmie zhodiť objednávku ani správu zákazníka — len sa nahlási.
 */
class StaffAlerts
{
    private const ORDER_MESSAGE_TITLES = [
        'message' => 'Nová správa od zákazníka',
        'approved' => 'Zákazník schválil grafiku',
        'changes_requested' => 'Zákazník žiada úpravu grafiky',
    ];

    public static function orderCreated(Order $order): void
    {
        self::send(self::orderRecipients($order), new StaffAlert('order',
            'Nová objednávka '.$order->serial_number, self::customerName($order), '/objednavky/'.$order->id.'/show'));
    }

    public static function orderMessage(OrderMessage $message): void
    {
        $order = $message->order;

        self::send(self::orderRecipients($order), new StaffAlert('order_message',
            (self::ORDER_MESSAGE_TITLES[$message->kind] ?? self::ORDER_MESSAGE_TITLES['message']).' – '.$order->serial_number,
            self::excerpt(self::customerName($order), $message->body), '/vyroba/'.$order->id));
    }

    public static function quoteRequested(SalesQuote $quote): void
    {
        self::send(self::salesRecipients(), new StaffAlert('quote_request',
            'Nový cenový dopyt '.$quote->number, self::excerpt($quote->displayName(), $quote->brief), '/predaj/ponuky/'.$quote->id));
    }

    public static function quoteMessage(SalesQuoteMessage $message): void
    {
        $quote = $message->quote;
        $heading = QuoteMessageReceived::HEADINGS[$message->kind] ?? QuoteMessageReceived::HEADINGS['message'];

        self::send(self::salesRecipients(), new StaffAlert('quote_message',
            $heading.' '.$quote->number, self::excerpt($quote->displayName(), $message->body), '/predaj/ponuky/'.$quote->id));
    }

    /** @param Collection<int, User> $users */
    private static function send(Collection $users, StaffAlert $alert): void
    {
        try {
            if ($users->isNotEmpty()) {
                Notification::send($users, $alert);
            }
        } catch (\Throwable $e) {
            report($e);
        }
    }

    /** @return Collection<int, User> */
    private static function orderRecipients(Order $order): Collection
    {
        $users = User::role('super-admin')->get();
        if ($order->user_id && ($owner = User::find($order->user_id)) && $owner->isStaff()) {
            $users->push($owner);
        }

        return $users->filter->isActive()->unique('id')->values();
    }

    /** @return Collection<int, User> */
    private static function salesRecipients(): Collection
    {
        return User::role(['super-admin', 'admin', 'manager', 'sales', 'warehouse'])->get()
            ->filter(fn (User $u) => $u->isStaff() && $u->can('orders.create') && $u->can('orders.viewAny'))
            ->values();
    }

    private static function customerName(Order $order): string
    {
        $billing = $order->billing;

        return ($billing->company ?? '') ?: (string) ($billing->name ?? '');
    }

    private static function excerpt(string $who, ?string $text): string
    {
        $text = Str::limit(trim(preg_replace('/\s+/u', ' ', (string) $text)), 160);

        return trim($who.($who !== '' && $text !== '' ? ': ' : '').$text);
    }
}
