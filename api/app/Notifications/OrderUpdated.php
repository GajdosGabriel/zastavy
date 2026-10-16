<?php

namespace App\Notifications;

use App\Models\Order;
use App\Models\User;
use App\Support\OrderThread;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class OrderUpdated extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly Order $order,
        public readonly array $changes = [],
        /** Voliteľný text obsluhy, napr. dôvod storna položky. */
        public readonly ?string $note = null,
    ) {}

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
            ->view('emails.orderUpdated', [
                'order'   => $this->order,
                'changes' => $this->changes,
                'note'    => $this->note,
            ]);
    }

    public function toArray($notifiable): array
    {
        return [];
    }
}
