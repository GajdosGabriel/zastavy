<?php

namespace App\Notifications;

use App\Models\Order;
use App\Models\Shipping;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class OrderPreparing extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public Order $order, public Shipping $shipping) {}

    public function via($notifiable)
    {
        return ['mail'];
    }

    public function toMail($notifiable)
    {
        return (new MailMessage)->from('obchod@zastavy-vlajky.sk', 'Gajdoš Gabriel – Reprezent')
            ->replyTo('obchod@zastavy-vlajky.sk', 'Gajdoš Gabriel – Reprezent')
            ->subject('Vašu objednávku pripravujeme v sklade – č. '.$this->order->serial_number)
            ->view('emails.orderPreparing', ['order' => $this->order, 'shipping' => $this->shipping]);
    }
}
