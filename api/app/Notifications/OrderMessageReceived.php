<?php

namespace App\Notifications;

use App\Models\OrderMessage;
use App\Support\OrderThread;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** Upozornenie obsluhe: zákazník napísal k objednávke, poslal pripomienky alebo schválil grafiku. */
class OrderMessageReceived extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public OrderMessage $entry) {}

    public function via($notifiable)
    {
        return ['mail'];
    }

    public function toMail($notifiable)
    {
        $order = $this->entry->order;

        return (new MailMessage)->from(OrderThread::FROM, OrderThread::FROM_NAME)
            ->subject(OrderThread::replySubject($order))
            ->withSymfonyMessage(fn ($m) => OrderThread::reply($m, $order, true))
            ->view('emails.orderMessageReceived', ['order' => $order, 'entry' => $this->entry,
                'url' => rtrim(config('app.frontend_url'), '/').'/vyroba/'.$order->id]);
    }
}
