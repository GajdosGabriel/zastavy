<?php

namespace App\Observers;

use App\Models\Order;
use App\Services\SystemLog\Recorder;

class OrderObserver
{
    /**
     * Handle the Order "created" event.
     *
     * @param  \App\Models\Order  $order
     * @return void
     */
    public function created(Order $order)
    {
        app(\App\Services\Account\AccountMailing::class)->event(\App\Enums\MailingTrigger::OrderCreated, $order, null, $order->created_at);
        Recorder::info('order', 'created', 'Vytvorená objednávka #'.$order->id,
            status: 'ok',
            recipient: $order->routeNotificationForMail(),
            userId: request()->user('sanctum')?->id,
            ip: app()->runningInConsole() ? null : request()->ip(),
            context: [
                'order_id' => $order->id,
                'order_uuid' => $order->uuid,
                'customer_id' => $order->customer_id,
                'customer_name' => $order->billingSnapshot()['company'] ?? $order->billingSnapshot()['name'] ?? null,
            ],
        );
    }

    /**
     * Handle the Order "updated" event.
     *
     * @param  \App\Models\Order  $order
     * @return void
     */
    public function updated(Order $order)
    {
        // Čakajúce maily k stornovanej objednávke v Accounte treba zrušiť.
        if ($order->wasChanged('status') && $order->status === \App\Enums\OrderStatus::Cancelled) {
            $this->cancelMailing($order);
        }
    }

    /**
     * Handle the Order "deleted" event.
     *
     * @param  \App\Models\Order  $order
     * @return void
     */
    public function deleted(Order $order)
    {
        $this->cancelMailing($order);
    }

    private function cancelMailing(Order $order): void
    {
        app(\App\Services\Account\AccountMailing::class)->cancel($order);
    }

    /**
     * Handle the Order "restored" event.
     *
     * @param  \App\Models\Order  $order
     * @return void
     */
    public function restored(Order $order)
    {
        //
    }

    /**
     * Handle the Order "force deleted" event.
     *
     * @param  \App\Models\Order  $order
     * @return void
     */
    public function forceDeleted(Order $order)
    {
        //
    }
}
