<?php

namespace App\Notifications;

use App\Models\SalesQuoteMessage;
use App\Support\OrderThread;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** Obsluhe: zákazník napísal k ponuke, žiada úpravu alebo ju odmietol. */
class QuoteMessageReceived extends Notification implements ShouldQueue
{
    use Queueable;

    public const HEADINGS = ['message' => 'Nová správa k ponuke', 'change_request' => 'Zákazník žiada úpravu ponuky', 'rejected' => 'Zákazník odmietol ponuku'];

    public function __construct(public SalesQuoteMessage $entry) {}

    public function via($notifiable)
    {
        return ['mail'];
    }

    public function toMail($notifiable)
    {
        $quote = $this->entry->quote;
        $heading = self::HEADINGS[$this->entry->kind] ?? self::HEADINGS['message'];

        return (new MailMessage)->from(OrderThread::FROM, OrderThread::FROM_NAME)
            ->replyTo($quote->customer['email'] ?? OrderThread::FROM)
            ->subject($heading.' '.$quote->number.' | '.$quote->displayName())
            ->view('emails.quoteMessageReceived', ['quote' => $quote, 'entry' => $this->entry, 'heading' => $heading,
                'url' => rtrim(config('app.frontend_url'), '/').'/predaj/ponuky/'.$quote->id]);
    }
}
