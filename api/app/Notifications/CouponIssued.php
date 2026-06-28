<?php

namespace App\Notifications;

use App\Models\Coupon;
use App\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class CouponIssued extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly Coupon $coupon,
        public readonly Order  $order,
    ) {}

    public function via($notifiable): array
    {
        return ['mail'];
    }

    public function toMail($notifiable): MailMessage
    {
        return (new MailMessage)
            ->from('obchod@zastavy-vlajky.sk', 'Gajdoš Gabriel – Reprezent')
            ->replyTo('obchod@zastavy-vlajky.sk', 'Gajdoš Gabriel – Reprezent')
            ->subject('Váš zľavový kupón na ďalší nákup')
            ->view('emails.couponIssued', [
                'coupon' => $this->coupon,
                'order'  => $this->order,
            ]);
    }

    public function toArray($notifiable): array
    {
        return [];
    }
}
