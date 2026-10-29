<?php

namespace App\Listeners;

use Illuminate\Mail\Events\MessageSending;
use Symfony\Component\Mime\Address;

/**
 * Mail z noreply@ dostane Reply-To na obchod (config mail.default_reply_to).
 *
 * Len keď si ho mail nenastavil sám – objednávkové maily smerujú odpoveď
 * na obchod@ alebo na zákazníka a to sa nemení.
 */
class DefaultReplyTo
{
    public function handle(MessageSending $event): void
    {
        $address = config('mail.default_reply_to.address');

        if (! filled($address) || $event->message->getReplyTo() !== []) {
            return;
        }

        $event->message->replyTo(new Address($address, (string) config('mail.default_reply_to.name')));
    }
}
