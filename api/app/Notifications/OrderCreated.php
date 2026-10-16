<?php

namespace App\Notifications;

use App\Models\Order;
use App\Models\User;
use App\Support\OrderThread;
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

    /**
     * @param  bool  $resent  dodatočné odoslanie z detailu objednávky — správa má vlastné
     *                        Message-ID a odpovedá na koreň vlákna. Druhú správu s rovnakým
     *                        Message-ID by Gmail ako duplikát zahodil.
     */
    public function __construct(Order $order, public bool $resent = false)
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
            ->subject($this->resent ? OrderThread::replySubject($this->order) : OrderThread::subject($this->order))
            ->replyTo('obchod@zastavy-vlajky.sk', 'Gajdoš Gabriel – Reprezent')
            ->view('emails.orderConfirmation', ['order' => $this->order, 'forAdmin' => $notifiable instanceof User])
            // Koreň vlákna objednávky: ďalšie e-maily k objednávke sa zaradia pod túto správu.
            ->withSymfonyMessage(fn ($m) => $this->resent
                ? OrderThread::reply($m, $this->order, $notifiable instanceof User)
                : OrderThread::root($m, $this->order, $notifiable instanceof User));

        // Odpoveď na admin kópiu ide zákazníkovi; odpoveď zákazníka na jeho
        // vlastné potvrdenie ostáva na obchod@zastavy-vlajky.sk.
        if ($notifiable instanceof User && ($customerEmail = $this->order->routeNotificationForMail())) {
            $message->replyTo = [];
            $message->replyTo($customerEmail, $this->order->billing->company ?? '');
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
