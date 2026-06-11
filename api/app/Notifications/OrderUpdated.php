<?php

namespace App\Notifications;

use App\Models\Order;
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
    ) {}

    public function via($notifiable): array
    {
        return ['mail'];
    }

    public function toMail($notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Zmena objednávky | ' . $this->order->customer->company)
            ->view('emails.orderUpdated', [
                'order'   => $this->order,
                'changes' => $this->changes,
            ]);
    }

    public function toArray($notifiable): array
    {
        return [];
    }
}
