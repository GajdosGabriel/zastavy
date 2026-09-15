<?php

namespace App\Notifications;

use App\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;
use Illuminate\Notifications\Messages\MailMessage;

/**
 * Adresa doručenia sa zmenila — pre sklad aj pre zákazníka.
 *
 * Nesie aj pôvodnú adresu: bez nej by príjemca videl len nový stav a nevedel by,
 * či sa zmenilo mesto alebo len telefón na príjemcu.
 */
class OrderDeliveryAddressChanged extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public Order $order, public array $previous)
    {
    }

    public function via($notifiable)
    {
        return ['mail'];
    }

    public function toMail($notifiable)
    {
        $this->order->loadMissing(['customer', 'shippingMethod']);

        return (new MailMessage)
            ->from('obchod@zastavy-vlajky.sk', 'Gajdoš Gabriel – Reprezent')
            ->subject('Zmena adresy doručenia | '.($this->order->serial_number ?: $this->order->billing?->company))
            ->replyTo('obchod@zastavy-vlajky.sk', 'Gajdoš Gabriel – Reprezent')
            ->view('emails.orderDeliveryAddressChanged', [
                'order' => $this->order,
                'previous' => $this->previous,
                'current' => $this->order->deliverySnapshot(),
            ]);
    }

    public function toArray($notifiable)
    {
        return [];
    }
}
