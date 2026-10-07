<?php

namespace App\Notifications;

use App\Models\Order;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class OrderCreated extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new notification instance.
     *
     * @return void
     */
    public $order;
    public function __construct(Order $order)
    {
        $this->order = $order;
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
        $this->order->load([
            'customer',
            'orderProducts.product',
            'shippingMethod',
            'paymentMethod',
        ]);

        $message = (new MailMessage)
            ->from('obchod@zastavy-vlajky.sk', 'Gajdoš Gabriel – Reprezent')
            ->subject("Objednávka | ". $this->order->billing->company)
            ->replyTo('obchod@zastavy-vlajky.sk', 'Gajdoš Gabriel – Reprezent')
            ->view('emails.orderConfirmation', ['order' => $this->order]);

        // Kópia pre admina ide cez druhý mailer; zákazník ostáva na hlavnom SMTP.
        if ($notifiable instanceof User && config('mail.mailers.admin.username')) {
            $message->mailer('admin')
                ->from(config('mail.mailers.admin.from_address') ?: config('mail.mailers.admin.username'), 'Gajdoš Gabriel – Reprezent');

            // Odpoveď na admin kópiu ide zákazníkovi; odpoveď zákazníka na jeho
            // vlastné potvrdenie ostáva na obchod@zastavy-vlajky.sk.
            if ($customerEmail = $this->order->routeNotificationForMail()) {
                $message->replyTo = [];
                $message->replyTo($customerEmail, $this->order->billing->company ?? '');
            }
        }

        return $message;
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
