<?php

namespace App\Services\EmailOrders;

use App\Jobs\ProcessEmailOrder;
use App\Models\EmailOrderDraft;
use App\Models\Order;
use App\Models\OrderMessage;
use App\Models\SalesQuote;
use App\Services\Account\AccountAttachments;
use App\Services\EmailOrderImport;
use App\Services\OpenAI\EmailOrderAI;
use App\Services\QuoteNumberService;
use App\Services\SystemLog\Recorder;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Objednávky z e-mailu.
 *
 *  1. receive()  Account pošle nový mail mimo vlákna objednávky (message.unassigned)
 *                → uloží sa ako návrh aj s prílohami a zaradí sa na spracovanie.
 *  2. process()  AI rozhodne, či ide o objednávku, dopyt na ponuku alebo nič, a pripraví
 *                návrh (EmailOrderImport). Dopyt vznikne hneď ako cenová ponuka (createQuote),
 *                úplná objednávka pri zapnutom auto_create tiež.
 *  3. attach()   Objednávka z návrhu (automaticky alebo z košíka): pôvodný mail sa
 *                stane prvou správou zákazníka v konverzácii objednávky a ďalšie
 *                maily k objednávke odídu do vlákna zákazníkovho mailu.
 */
class EmailOrderInbox
{
    public function __construct(
        private readonly EmailOrderImport $import,
        private readonly EmailOrderAutoCreate $autoCreate,
    ) {}

    /**
     * @param  array<string, mixed>  $conversation
     * @param  array<string, mixed>  $message
     */
    public function receive(array $conversation, array $message): void
    {
        if (! config('account.email_orders.enabled') || empty($message['id']) || ($message['direction'] ?? 'in') !== 'in') {
            return;
        }

        // Webhook môže prísť dvakrát (opakovanie po výpadku).
        if (EmailOrderDraft::where('account_message_id', $message['id'])->exists()) {
            return;
        }

        $paths = [];

        try {
            $draft = DB::transaction(function () use ($conversation, $message, &$paths) {
                $draft = EmailOrderDraft::create([
                    'account_message_id' => $message['id'],
                    'account_conversation_id' => $conversation['id'] ?? null,
                    'from_email' => Str::limit((string) ($message['from'] ?? ''), 150, '') ?: null,
                    'from_name' => Str::limit((string) ($message['from_name'] ?? ''), 150, '') ?: null,
                    'subject' => Str::limit((string) ($message['subject'] ?? ''), 255, '') ?: null,
                    'body' => trim((string) ($message['text'] ?? '')),
                    'received_at' => isset($message['created_at']) ? Carbon::parse($message['created_at']) : now(),
                ]);

                foreach ($message['attachments'] ?? [] as $file) {
                    AccountAttachments::store($draft, $file, 'email-orders/'.$draft->id, $paths);
                }

                return $draft;
            });
        } catch (UniqueConstraintViolationException) {
            Storage::disk('local')->delete($paths);

            return;
        } catch (\Throwable $e) {
            Storage::disk('local')->delete($paths);
            throw $e;
        }

        ProcessEmailOrder::dispatch($draft->id)->afterCommit();
    }

    public function process(EmailOrderDraft $draft): void
    {
        if ($draft->status !== EmailOrderDraft::PENDING) {
            return;
        }

        $preview = $this->import->preview($this->text($draft), inbox: true);

        if ($preview === null) {
            $draft->update(['status' => EmailOrderDraft::NOT_ORDER, 'error' => null]);

            return;
        }

        if ($preview['kind'] === 'quote') {
            $draft->update(['preview' => $preview, 'error' => null]);
            $this->createQuote($draft);

            return;
        }

        $reasons = $this->autoCreate->reasons($draft, $preview);
        $draft->update(['status' => EmailOrderDraft::DRAFT, 'preview' => $preview, 'reasons' => $reasons, 'error' => null]);

        Recorder::info('order', 'email_order_draft', 'Objednávka z e-mailu: '.($draft->subject ?? ''), status: $reasons ? 'draft' : 'complete',
            recipient: $draft->from_email, context: ['draft' => $draft->id, 'reasons' => $reasons]);

        if ($reasons === []) {
            try {
                $this->autoCreate->create($draft);
            } catch (ValidationException $e) {
                // Napr. variant medzičasom vyradený – návrh ostane obsluhe.
                $draft->update(['reasons' => array_values(array_unique(array_merge(...array_values($e->errors()))))]);
            }
        }
    }

    /**
     * Objednávka vznikla z návrhu. Volá sa v transakcii vytvorenia objednávky,
     * takže potvrdenie (odchádza po commite) už nájde konverzáciu v Accounte.
     */
    public function attach(EmailOrderDraft $draft, Order $order, bool $auto): void
    {
        $draft = EmailOrderDraft::whereKey($draft->id)->lockForUpdate()->firstOrFail();

        if ($draft->order_id || $draft->sales_quote_id) {
            throw ValidationException::withMessages(['email_order_draft_id' => ['Z tohto e-mailu už objednávka alebo ponuka vznikla.']]);
        }

        $message = OrderMessage::create([
            'order_id' => $order->id,
            'direction' => 'customer',
            'kind' => 'message',
            'author' => Str::limit($draft->from_name ?: ($draft->from_email ?: 'e-mail'), 150, ''),
            'body' => trim(($draft->subject ? $draft->subject."\n\n" : '').$draft->body),
            // Ručne vytvorenú objednávku obsluha práve čítala; automatickú ešte nevidela.
            'read_at' => $auto ? null : now(),
            'account_message_id' => $draft->account_message_id,
            'created_at' => $draft->received_at ?? now(),
        ]);

        $draft->attachments()->update([
            'attachable_type' => $message->getMorphClass(),
            'attachable_id' => $message->id,
        ]);

        $from = trim(($draft->from_name ? $draft->from_name.' ' : '').($draft->from_email ? '<'.$draft->from_email.'>' : ''));
        $line = 'Objednávka z e-mailu'.($from ? ' od '.$from : '').', '.($draft->received_at ?? now())->format('d.m.Y H:i')
            .' – pôvodný text v konverzácii'.($auto ? ' (vytvorená automaticky)' : '').'.';
        $order->update(['note' => trim($line."\n".$order->note)]);

        $draft->update(['status' => EmailOrderDraft::CREATED, 'order_id' => $order->id, 'auto_created' => $auto]);

        Recorder::info('order', 'email_order_created', 'Objednávka z e-mailu '.($order->serial_number ?? '#'.$order->id),
            status: $auto ? 'auto' : 'manual', recipient: $draft->from_email, context: ['draft' => $draft->id, 'order' => $order->uuid]);
    }

    /**
     * Dopyt na cenovú ponuku z e-mailu – rovnako ako dopyt z webu vznikne hneď
     * ako ponuka v stave „dopyt“; ceny a odoslanie rieši obsluha v Cenových ponukách.
     * Pôvodný mail je prvou (neprečítanou) správou zákazníka v konverzácii ponuky.
     */
    public function createQuote(EmailOrderDraft $draft): SalesQuote
    {
        return DB::transaction(function () use ($draft) {
            $draft = EmailOrderDraft::whereKey($draft->id)->lockForUpdate()->firstOrFail();

            if ($draft->salesQuote) {
                return $draft->salesQuote;
            }

            $preview = $draft->preview;
            $customer = array_intersect_key($preview['customer'] ?? [], array_flip(EmailOrderAI::FIELDS));
            // Odpovedá sa tomu, kto písal, aj keď AI e-mail v texte nenašla.
            $customer['email'] = ($customer['email'] ?? '') ?: (string) $draft->from_email;
            $customer['name'] = ($customer['name'] ?? '') ?: (string) $draft->from_name;

            $items = [];
            $unmatched = [];
            foreach ($preview['items'] ?? [] as $item) {
                if ($item['cart']) {
                    $items[] = ['variant_id' => $item['cart']['variant_id'], 'name' => $item['cart']['name'].' / '.$item['cart']['variant_name'], 'quantity' => $item['cart']['input_order']];
                } else {
                    $unmatched[] = '- '.$item['description'].(($item['quantity'] ?? null) ? ' ('.$item['quantity'].' ks)' : '');
                }
            }
            $brief = trim(implode("\n\n", array_filter([
                trim((string) ($preview['note'] ?? '')),
                $unmatched ? "Položky bez produktu v katalógu:\n".implode("\n", $unmatched) : null,
            ])));

            $token = Str::random(64);
            $quote = SalesQuote::create([
                'uuid' => (string) Str::uuid(), 'number' => app(QuoteNumberService::class)->next(),
                'token_hash' => hash('sha256', $token), 'token' => $token, 'token_expires_at' => now()->addDays(90),
                'customer' => $customer,
                'customer_id' => ($preview['customer_status'] ?? null) === 'existing' ? ($preview['customer']['id'] ?? null) : null,
                'title' => Str::limit((string) $draft->subject, 200, '') ?: null,
                'brief' => Str::limit($brief ?: 'Dopyt z e-mailu – pozri konverzáciu.', 10000, ''),
                'requested_items' => $items,
                'internal_note' => implode("\n", $preview['warnings'] ?? []) ?: null,
                'source' => 'email',
            ]);

            $message = $quote->messages()->create([
                'direction' => 'customer',
                'kind' => 'message',
                'author' => Str::limit($draft->from_name ?: ($draft->from_email ?: 'e-mail'), 150, ''),
                'body' => Str::limit(trim(($draft->subject ? $draft->subject."\n\n" : '').$draft->body), 20000, ''),
                'created_at' => $draft->received_at ?? now(),
            ]);

            // Podklady zákazníka (logo, sken) patria k ponuke rovnako ako pri dopyte z webu.
            $draft->attachments()->update(['attachable_type' => $quote->getMorphClass(), 'attachable_id' => $quote->id]);

            $draft->update(['status' => EmailOrderDraft::CREATED, 'sales_quote_id' => $quote->id, 'auto_created' => true]);

            Recorder::info('quote', 'email_quote_created', 'Dopyt z e-mailu '.$quote->number, status: 'requested',
                recipient: $draft->from_email, context: ['draft' => $draft->id, 'quote' => $quote->id, 'message' => $message->id]);

            return $quote;
        });
    }

    /** Konverzácia v Accounte, do ktorej patria maily k objednávke vytvorenej z e-mailu. */
    public static function conversationFor(Order $order): ?string
    {
        return EmailOrderDraft::where('order_id', $order->id)->whereNotNull('account_conversation_id')->value('account_conversation_id');
    }

    private function text(EmailOrderDraft $draft): string
    {
        $from = trim(($draft->from_name ?? '').' <'.($draft->from_email ?? '').'>');

        return mb_substr("Od: {$from}\nPredmet: {$draft->subject}\n\n{$draft->body}", 0, 20000);
    }
}
