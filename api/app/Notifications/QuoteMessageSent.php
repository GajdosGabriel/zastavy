<?php

namespace App\Notifications;

use App\Models\SalesQuoteMessage;
use App\Support\OrderThread;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** Zákazníkovi: odpoveď obsluhy k cenovej ponuke so súkromným odkazom. */
class QuoteMessageSent extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public SalesQuoteMessage $entry) {}

    public function via($notifiable)
    {
        return ['mail'];
    }

    public function toMail($notifiable)
    {
        $quote = $this->entry->quote;

        // Odkaz sa skladá až tu, aby token neležal vo fronte v čitateľnej podobe.
        return (new MailMessage)->from(OrderThread::FROM, OrderThread::FROM_NAME)
            ->replyTo(OrderThread::FROM, OrderThread::FROM_NAME)
            ->subject('Re: Cenová ponuka č. '.$quote->number.($quote->displayName() ? ' | '.$quote->displayName() : ''))
            ->view('emails.quoteMessage', ['quote' => $quote, 'entry' => $this->entry, 'url' => $quote->shareUrl()]);
    }
}
