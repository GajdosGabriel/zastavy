<?php

namespace App\Notifications;

use Illuminate\Notifications\Notification;

/**
 * Záznam v histórii upozornení obsluhy (len databáza, žiadny e-mail).
 * Posiela sa cez App\Services\StaffAlerts, ktorý vyberie príjemcov.
 *
 * `url` je cesta vo frontende (napr. /vyroba/12), nie celá adresa.
 */
class StaffAlert extends Notification
{
    public const KINDS = ['order', 'order_message', 'quote_request', 'quote_message'];

    public function __construct(
        public string $kind,
        public string $title,
        public ?string $body,
        public string $url,
    ) {}

    public function via($notifiable)
    {
        return ['database'];
    }

    public function toArray($notifiable)
    {
        return ['kind' => $this->kind, 'title' => $this->title, 'body' => $this->body, 'url' => $this->url];
    }
}
