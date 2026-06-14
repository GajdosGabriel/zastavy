<?php

namespace App\Notifications;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class UserInvited extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly User $invitedUser,
        public readonly string $temporaryPassword,
        public readonly array $roles = [],
        public readonly ?string $verificationUrl = null,
    ) {}

    public function via($notifiable): array
    {
        return ['mail'];
    }

    public function toMail($notifiable): MailMessage
    {
        return (new MailMessage)
            ->from('obchod@zastavy-vlajky.sk', 'Gajdoš Gabriel – Reprezent')
            ->subject('Váš účet bol vytvorený')
            ->view('emails.userInvited', [
                'user'              => $this->invitedUser,
                'temporaryPassword' => $this->temporaryPassword,
                'roles'             => $this->roles,
                'loginUrl'          => env('FRONTEND_URL', config('app.url')) . '/login',
                'verificationUrl'   => $this->verificationUrl,
            ]);
    }
}
