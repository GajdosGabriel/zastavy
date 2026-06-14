<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class ResetPassword extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly string $token) {}

    public function via(mixed $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(mixed $notifiable): \Illuminate\Notifications\Messages\MailMessage
    {
        $url = rtrim(env('FRONTEND_URL', config('app.url')), '/') . '/reset-password'
            . '?token=' . urlencode($this->token)
            . '&email=' . urlencode($notifiable->getEmailForPasswordReset());

        return (new \Illuminate\Notifications\Messages\MailMessage)
            ->from('obchod@zastavy-vlajky.sk', 'Gajdoš Gabriel – Reprezent')
            ->subject('Obnovenie hesla')
            ->view('emails.resetPassword', [
                'url'  => $url,
                'user' => $notifiable,
            ]);
    }
}
