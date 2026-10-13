<?php

namespace App\Notifications;

use App\Models\OrderMessage;
use App\Support\OrderThread;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** Správa obsluhy zákazníkovi vo vlákne objednávky; odpovedá sa cez súkromný odkaz. */
class OrderMessageSent extends Notification implements ShouldQueue
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

        // Odkaz sa skladá až tu, aby token neležal vo fronte v čitateľnej podobe.
        return (new MailMessage)->from(OrderThread::FROM, OrderThread::FROM_NAME)
            ->replyTo(OrderThread::FROM, OrderThread::FROM_NAME)
            ->subject(OrderThread::replySubject($order))
            ->withSymfonyMessage(fn ($m) => OrderThread::reply($m, $order))
            ->view('emails.orderMessage', ['order' => $order, 'entry' => $this->entry, 'url' => $order->messagesUrl()]);
    }
}
