<?php

namespace App\Notifications;

use App\Models\Order;
use App\Models\User;
use App\Support\OrderThread;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class OrderCancelled extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly Order $order) {}

    public function via($notifiable): array
    {
        return ['mail'];
    }

    public function toMail($notifiable): MailMessage
    {
        $this->order->load(['customer', 'orderProducts.product']);

        return (new MailMessage)
            ->from('obchod@zastavy-vlajky.sk', 'Gajdoš Gabriel – Reprezent')
            ->subject(OrderThread::replySubject($this->order))
            ->withSymfonyMessage(fn ($m) => OrderThread::reply($m, $this->order, $notifiable instanceof User))
            ->replyTo('obchod@zastavy-vlajky.sk', 'Gajdoš Gabriel – Reprezent')
            ->view('emails.orderCancelled', ['order' => $this->order]);
    }

    public function toArray($notifiable): array
    {
        return [];
    }
}
