<?php

namespace App\Services\Account;

use App\Listeners\SystemLogSubscriber;
use App\Models\Order;
use App\Models\OrderMessage;
use App\Services\SystemLog\Recorder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Udalosti o e-mailoch z Accountu (`message.*`) pre objednávky.
 *
 *  - message.received  zákazník odpovedal e-mailom → správa zákazníka
 *                      v konverzácii objednávky (ako keby písal na webe),
 *  - message.sent      obsluha odpísala z Inboxu Accountu → správa obsluhy
 *                      (maily poslané odtiaľto už v konverzácii sú),
 *  - message.bounced / message.failed → záznam v histórii e-mailov.
 *
 * Objednávka, ktorá medzičasom zmizla, sa ticho preskočí – Account by inak
 * doručenie opakoval celé hodiny.
 */
class AccountMessages
{
    /**
     * @param  array<string, mixed>  $conversation
     * @param  array<string, mixed>  $message
     */
    public function handle(string $event, array $conversation, array $message): void
    {
        if (($conversation['ref']['type'] ?? null) !== 'order') {
            return;
        }

        $order = Order::where('uuid', $conversation['ref']['id'] ?? '')->first();

        if (! $order) {
            return;
        }

        match ($event) {
            'message.received' => $this->received($order, $message),
            'message.sent' => $this->sentFromInbox($order, $message),
            'message.bounced', 'message.failed' => $this->undelivered($order, $event, $message),
            default => null,
        };
    }

    /** @param array<string, mixed> $message */
    private function received(Order $order, array $message): void
    {
        if ($this->alreadyStored($order, $message)) {
            return;
        }

        $paths = [];

        try {
            DB::transaction(function () use ($order, $message, &$paths) {
                $row = OrderMessage::create([
                    'order_id' => $order->id,
                    'direction' => 'customer',
                    'kind' => 'message',
                    'author' => Str::limit(($message['from_name'] ?? null) ?: ($message['from'] ?? 'e-mail'), 150, ''),
                    'body' => trim((string) ($message['reply_text'] ?? $message['text'] ?? '')),
                    'account_message_id' => $message['id'] ?? null,
                ]);

                foreach ($message['attachments'] ?? [] as $file) {
                    AccountAttachments::store($row, $file, 'order-messages/'.$row->order_id, $paths);
                }
            });
        } catch (\Throwable $e) {
            Storage::disk('local')->delete($paths);
            throw $e;
        }

        Recorder::info('mail', 'customer_replied', 'Odpoveď zákazníka e-mailom', status: 'received',
            recipient: $message['from'] ?? null, context: ['order' => $order->uuid, 'message' => $message['id'] ?? null]);
    }

    /** @param array<string, mixed> $message */
    private function sentFromInbox(Order $order, array $message): void
    {
        if (($message['source'] ?? null) !== 'inbox' || $this->alreadyStored($order, $message)) {
            return;
        }

        OrderMessage::create([
            'order_id' => $order->id,
            'direction' => 'staff',
            'kind' => 'message',
            'author' => Str::limit((string) ($message['sent_by'] ?? 'Obsluha'), 150, ''),
            'body' => trim((string) ($message['text'] ?? '')),
            'read_at' => now(),
            'account_message_id' => $message['id'] ?? null,
        ]);

        SystemLogSubscriber::recordOrderEmail(null, 'InboxReply', (string) ($message['subject'] ?? ''),
            $message['to'] ?? null, 'sent', body: $message['html'] ?? null, orderId: $order->id);
    }

    /** @param array<string, mixed> $message */
    private function undelivered(Order $order, string $event, array $message): void
    {
        $reason = $event === 'message.bounced' ? 'Nedoručiteľné' : 'Neodoslané';

        SystemLogSubscriber::recordOrderEmail(null, $message['meta']['notification'] ?? null, (string) ($message['subject'] ?? ''),
            $message['to'] ?? null, 'failed', error: trim($reason.': '.($message['error'] ?? '')), orderId: $order->id);
    }

    /** Webhook môže prísť dvakrát (opakovanie po výpadku) – správa nesmie vzniknúť dvakrát. */
    private function alreadyStored(Order $order, array $message): bool
    {
        return ! empty($message['id'])
            && OrderMessage::where('order_id', $order->id)->where('account_message_id', $message['id'])->exists();
    }
}
