<?php

namespace App\Notifications;

use App\Models\Order;
use App\Models\OrderReturn;
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

        $subjects = [
            'not_accepted' => 'Vrátená zásielka – objednávka č. ' . $this->order->serial_number,
            'damaged'      => 'Vrátenie poškodeného tovaru – objednávka č. ' . $this->order->serial_number,
            'wrong_item'   => 'Vrátenie tovaru – objednávka č. ' . $this->order->serial_number,
            'other'        => 'Vrátenie tovaru – objednávka č. ' . $this->order->serial_number,
        ];

        $subject = $subjects[$this->orderReturn->reason] ?? ('Vrátenie tovaru – objednávka č. ' . $this->order->serial_number);

        return (new MailMessage)
            ->from('obchod@zastavy-vlajky.sk', 'Gajdoš Gabriel – Reprezent')
            ->subject($subject)
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
