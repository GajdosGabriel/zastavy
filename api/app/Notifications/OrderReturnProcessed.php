<?php

namespace App\Notifications;

use App\Models\Order;
use App\Models\OrderReturn;
use App\Models\User;
use App\Support\OrderThread;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class OrderReturnProcessed extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly Order $order,
        public readonly OrderReturn $orderReturn
    ) {}

    public function via($notifiable): array
    {
        return ['mail'];
    }

    public function toMail($notifiable): MailMessage
    {
        $this->order->load(['customer', 'orderProducts.product']);
        $this->orderReturn->load(['items.orderProduct.product']);

        return (new MailMessage)
            ->from('obchod@zastavy-vlajky.sk', 'Gajdoš Gabriel – Reprezent')
            ->subject(OrderThread::replySubject($this->order))
            ->withSymfonyMessage(fn ($m) => OrderThread::reply($m, $this->order, $notifiable instanceof User))
            ->replyTo('obchod@zastavy-vlajky.sk', 'Gajdoš Gabriel – Reprezent')
            ->view('emails.orderReturn', [
                'order'       => $this->order,
                'orderReturn' => $this->orderReturn,
            ]);
    }

    public function toArray($notifiable): array
    {
        return [];
    }
}
