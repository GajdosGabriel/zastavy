<?php

namespace App\Notifications;

use App\Models\SalesQuote;
use App\Services\QuotePdf;
use App\Support\OrderThread;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** Nová verzia cenovej ponuky so súkromným odkazom na prijatie. */
class QuoteOffered extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public SalesQuote $quote) {}

    public function via($notifiable)
    {
        return ['mail'];
    }

    public function toMail($notifiable)
    {
        $quote = $this->quote->fresh();
        $version = $quote->sentVersions()->firstOrFail();

        $pdf = app(QuotePdf::class);

        // Odkaz sa skladá až tu, aby token neležal vo fronte v čitateľnej podobe.
        return (new MailMessage)->from(OrderThread::FROM, OrderThread::FROM_NAME)
            ->attachData($pdf->render($quote, $version), $pdf->filename($quote, $version), ['mime' => 'application/pdf'])
            ->replyTo(OrderThread::FROM, OrderThread::FROM_NAME)
            ->subject('Cenová ponuka č. '.$quote->number.($quote->displayName() ? ' | '.$quote->displayName() : ''))
            ->view('emails.quoteOffered', ['quote' => $quote, 'version' => $version, 'url' => $url = $quote->shareUrl(), 'acceptUrl' => $url.'&prijat=1',
                'shopUrl' => rtrim(config('app.frontend_url'), '/')]);
    }
}
