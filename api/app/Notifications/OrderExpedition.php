<?php

namespace App\Notifications;

use App\Models\Order;
use App\Models\Shipping;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class OrderExpedition extends Notification implements ShouldQueue
{
    use Queueable;

    public $order;
    public $shipping;

    public function __construct(Order $order, ?Shipping $shipping = null)
    {
        $this->order = $order;
        $this->shipping = $shipping;
    }

    /**
     * Get the notification's delivery channels.
     *
     * @param  mixed  $notifiable
     * @return array
     */
    public function via($notifiable)
    {
        return ['mail'];
    }

    /**
     * Get the mail representation of the notification.
     *
     * @param  mixed  $notifiable
     * @return \Illuminate\Notifications\Messages\MailMessage
     */
    public function toMail($notifiable)
    {
        $this->order->load(['customer', 'orderProducts.product', 'shippingMethod']);

        if ($this->shipping) {
            $this->shipping->load(['stocks.orderProduct.product']);
        }

        $isPartial = !$this->order->isFinished();

        return (new MailMessage)
            ->from('obchod@zastavy-vlajky.sk', 'Gajdoš Gabriel – Reprezent')
            ->subject($isPartial
                ? 'Čiastočná expedícia objednávky č. ' . $this->order->serial_number
                : 'Vaša objednávka bola odoslaná – č. ' . $this->order->serial_number)
            ->replyTo('obchod@zastavy-vlajky.sk', 'Gajdoš Gabriel – Reprezent')
            ->view('emails.orderExpedition', [
                'order'    => $this->order,
                'shipping' => $this->shipping,
            ]);
    }

    /**
     * Get the array representation of the notification.
     *
     * @param  mixed  $notifiable
     * @return array
     */
    public function toArray($notifiable)
    {
        return [
            //
        ];
    }
}
