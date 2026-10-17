<?php

namespace App\Services\Account;

use App\Listeners\SystemLogSubscriber;
use App\Models\Order;
use App\Services\SystemLog\Recorder;
use App\Support\OrderThread;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Symfony\Component\Mime\Email;

/**
 * E-mail zákazníkovi k objednávke cez Account.
 *
 * Notifikácia sa vyskladá ako doteraz (`toMail()`, šablóny v resources/views/emails),
 * ale odíde zo schránky obchod@ v Accounte. Account drží vlákno: prvý mail
 * nesie `thread_id` = pôvodné Message-ID objednávky (OrderThread::id), takže
 * zákazníkovi ostane jedna konverzácia so staršími mailmi a aj odpoveď na
 * starý mail sa spáruje so správnou objednávkou.
 *
 * Históriu e-mailov objednávky (OrderEmail) zapisuje sám – lokálne udalosti
 * MessageSent pri tejto ceste nenastanú.
 */
class OrderMailer
{
    /** Hlavičky, ktoré Account prijme (odhlásenie jedným klikom). */
    private const HEADERS = ['List-Unsubscribe', 'List-Unsubscribe-Post'];

    public function __construct(private readonly AccountClient $account) {}

    public function send(Order $order, Notification $notification): void
    {
        $to = $order->routeNotificationForMail($notification);

        if (! $to) {
            return;
        }

        /** @var MailMessage $mail */
        $mail = $notification->toMail($order);
        $subject = (string) ($mail->subject ?: OrderThread::subject($order));
        $html = (string) $mail->render();
        $type = class_basename($notification);

        $payload = array_filter([
            'to' => $to,
            'to_name' => $order->billing->name ?? null,
            'subject' => $subject,
            'html' => $html,
            'ref' => self::ref($order),
            'kind' => 'order.'.$type,
            'thread_id' => OrderThread::id($order),
            'meta' => ['notification' => $type],
            'headers' => $this->headers($mail),
            'attachments' => $this->attachments($mail),
        ], fn ($value) => $value !== null && $value !== []);

        try {
            $result = $this->account->sendMessage($payload);
        } catch (AccountMessageRejected $e) {
            SystemLogSubscriber::recordOrderEmail(null, $type, $subject, $to, 'failed', error: $e->getMessage(), body: $html, orderId: $order->id);
            Recorder::warning('mail', 'rejected', $subject, status: $e->reason, recipient: $to,
                context: ['class' => $notification::class, 'via' => 'account', 'order' => $order->uuid]);

            return;
        }

        SystemLogSubscriber::recordOrderEmail(null, $type, $subject, $to, 'sent', body: $html, orderId: $order->id);
        Recorder::info('mail', 'sent', $subject, status: 'sent', recipient: $to, context: [
            'class' => $notification::class,
            'via' => 'account',
            'order' => $order->uuid,
            'conversation' => $result['conversation']['id'] ?? null,
        ]);
    }

    /** Referencia objednávky pre Account – podľa nej príde odpoveď späť. */
    public static function ref(Order $order): array
    {
        $number = $order->serial_number ? 'č. '.$order->serial_number : '#'.$order->id;
        $name = $order->billing->company ?: ($order->billing->name ?? null);

        return [
            'type' => 'order',
            'id' => $order->uuid,
            'label' => trim("Objednávka {$number}".($name ? " | {$name}" : '')),
            'url' => rtrim((string) config('app.frontend_url'), '/').'/objednavky/'.$order->id.'/show',
        ];
    }

    /** @return array<string, string> */
    private function headers(MailMessage $mail): array
    {
        if ($mail->callbacks === []) {
            return [];
        }

        $email = new Email;

        foreach ($mail->callbacks as $callback) {
            $callback($email);
        }

        $headers = [];

        foreach (self::HEADERS as $name) {
            if ($email->getHeaders()->has($name)) {
                $headers[$name] = $email->getHeaders()->get($name)->getBodyAsString();
            }
        }

        return $headers;
    }

    /** @return array<int, array{filename: string, mime: ?string, content: string}> */
    private function attachments(MailMessage $mail): array
    {
        $files = [];

        foreach ($mail->rawAttachments as $raw) {
            $files[] = ['filename' => $raw['name'], 'mime' => $raw['options']['mime'] ?? null, 'content' => base64_encode($raw['data'])];
        }

        foreach ($mail->attachments as $file) {
            if (is_string($file['file']) && is_file($file['file'])) {
                $files[] = [
                    'filename' => $file['options']['as'] ?? basename($file['file']),
                    'mime' => $file['options']['mime'] ?? null,
                    'content' => base64_encode((string) file_get_contents($file['file'])),
                ];
            }
        }

        return $files;
    }
}
