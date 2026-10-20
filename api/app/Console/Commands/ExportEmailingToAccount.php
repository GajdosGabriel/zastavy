<?php

namespace App\Console\Commands;

use App\Models\Order;
use App\Services\Account\AccountMailing;
use App\Services\Account\OrderMailer;
use App\Support\OrderThread;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\URL;

/**
 * Export lokálneho emailingu pre prechod do Accountu.
 *
 *   php artisan emailing:export-account storage/app/emailing.json
 *   (v Accounte) php artisan mailing:import zastavy emailing.json
 *
 * Tokeny odhlásenia, odkazov a otvorení sa prenášajú bez zmeny – staré
 * odkazy potom zastavy presmerujú do Accountu (ACCOUNT_MAILING=true).
 * Layouty sa prevedú na HTML so značkami Accountu ({{heading}}, {{{content}}}…).
 */
class ExportEmailingToAccount extends Command
{
    protected $signature = 'emailing:export-account {file : kam zapísať JSON}';

    protected $description = 'Vyexportuje kontakty, šablóny, kampane a doručenia emailingu pre import do Accountu.';

    public function handle(): int
    {
        $orders = Order::withTrashed()
            ->whereIn('id', DB::table('mailing_deliveries')->where('event_order_id', '>', 0)->distinct()->pluck('event_order_id'))
            ->get()
            ->keyBy('id');

        $data = [
            'layouts' => $this->layouts(),
            'templates' => DB::table('mailing_templates')->orderBy('id')->get()->map(fn ($t) => [
                'name' => $t->name, 'layout' => $t->layout ?? 'default', 'subject' => $t->subject, 'heading' => $t->heading,
                'body' => $t->body, 'button_label' => $t->button_label, 'button_url' => $t->button_url,
            ])->all(),
            'contacts' => DB::table('mailing_contacts')->orderBy('id')->get()->map(fn ($c) => [
                'email' => $c->email, 'name' => $c->name, 'permission_note' => $c->permission_note,
                'permission_at' => $this->date($c->permission_at), 'token' => $c->token,
                'unsubscribed_at' => $this->date($c->unsubscribed_at), 'bounced_at' => $this->date($c->bounced_at),
                'bounce_type' => $c->bounce_type, 'bounce_reason' => $c->bounce_reason, 'soft_bounces' => $c->soft_bounces,
                'created_at' => $this->date($c->created_at),
            ])->all(),
            'campaigns' => DB::table('mailing_campaigns')->orderBy('id')->get()->map(fn ($c) => [
                'id' => $c->id, 'name' => $c->name, 'subject' => $c->subject, 'heading' => $c->heading, 'body' => $c->body,
                'button_label' => $c->button_label, 'button_url' => $c->button_url, 'coupon_code' => $c->coupon_code,
                'coupon_valid_to' => $c->coupon_id ? optional(\App\Models\Coupon::find($c->coupon_id)?->valid_to)->toDateString() : null,
                'status' => $c->status, 'scheduled_at' => $this->date($c->scheduled_at), 'layout' => $c->layout ?? 'default',
                'trigger_event' => $c->trigger_event, 'delay_hours' => $c->delay_hours,
                'track_clicks' => (bool) $c->track_clicks, 'track_opens' => (bool) $c->track_opens,
                'reply_to_order' => (bool) ($c->reply_to_order ?? false), 'quote_event' => (bool) ($c->quote_event ?? true),
                'created_at' => $this->date($c->created_at),
            ])->all(),
            'deliveries' => DB::table('mailing_deliveries as d')->leftJoin('mailing_contacts as c', 'c.id', '=', 'd.contact_id')
                ->orderBy('d.id')->select('d.*')->selectRaw('COALESCE(d.email, c.email) as recipient')->get()
                ->filter(fn ($d) => filled($d->recipient))
                ->map(fn ($d) => $this->delivery($d, $orders[$d->event_order_id] ?? null))->values()->all(),
            'links' => DB::table('mailing_links')->orderBy('id')->get()->map(fn ($l) => [
                'delivery_id' => $l->delivery_id, 'position' => $l->position, 'token' => $l->token, 'url' => $l->url,
                'label' => $l->label, 'click_count' => $l->click_count, 'clicked_at' => $this->date($l->clicked_at),
            ])->all(),
        ];

        file_put_contents($this->argument('file'), json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

        $this->table(['Čo', 'Počet'], collect($data)->map(fn ($rows, $key) => [$key, count($rows)])->values()->all());
        $this->info('Uložené do '.$this->argument('file'));

        return self::SUCCESS;
    }

    /** @return array<string, mixed> */
    private function delivery(object $d, ?Order $order): array
    {
        $shipping = (int) ($d->event_shipping_id ?? 0);

        return [
            'id' => $d->id,
            'campaign_id' => $d->campaign_id,
            'email' => mb_strtolower($d->recipient),
            'reply' => $d->contact_id === null,
            'status' => $d->status,
            'ref_type' => $order ? 'order' : null,
            'ref_id' => $order?->uuid,
            'ref_label' => $order ? OrderMailer::ref($order)['label'] : null,
            'ref_url' => $order ? OrderMailer::ref($order)['url'] : null,
            // Rovnaký kľúč, aký pošle AccountMailing – nová udalosť nič nezdvojí.
            'dedupe_key' => $order ? AccountMailing::dedupeKey($order).($shipping ? ':shipping:'.$shipping : '') : '',
            'thread_id' => $order ? OrderThread::id($order) : null,
            'variables' => $order ? [
                'cislo_objednavky' => $order->serial_number,
                'firma' => $order->billing->company ?: ($order->billing->name ?? null),
                'odkaz_objednavky' => $order->publicUrl(),
            ] : [],
            'event_at' => $d->due_at ? $this->date($d->due_at) : null,
            'due_at' => $this->date($d->due_at),
            'attempted_at' => $this->date($d->attempted_at),
            'sent_at' => $this->date($d->sent_at),
            'error' => $d->error,
            'open_token' => $d->open_token,
            'opened_at' => $this->date($d->opened_at),
            'clicked_at' => $this->date($d->clicked_at),
            'open_count' => $d->open_count,
            'created_at' => $this->date($d->created_at),
        ];
    }

    /**
     * Predvolený layout je prepísaný ručne (oslovenie, kupón a tlačidlo dodá
     * Account v {{{content}}}). Layouty s grafikou sa vykreslia so zástupnými
     * hodnotami a tie sa nahradia značkami – obrázky produktov ostanú ako
     * absolútne adresy na zastavy.
     *
     * @return array<int, array{key: string, name: string, html: string}>
     */
    private function layouts(): array
    {
        $service = app(\App\Services\EmailingService::class);
        $layouts = [['key' => 'default', 'name' => 'Zástavy & Vlajky', 'html' => $this->defaultLayout()]];

        foreach (['spisor' => 'Spisor.eu', 'flags-catalog' => 'Vlajky a štátne symboly'] as $key => $name) {
            $html = $service->html((object) [
                'layout' => $key, 'heading' => '[[heading]]', 'body' => '[[body]]', 'subject' => '[[subject]]',
                'button_label' => '[[button_label]]', 'button_url' => 'https://button.invalid/', 'coupon_code' => null,
            ], (object) ['email' => '[[contact_email]]', 'name' => null, 'token' => str_repeat('x', 64)]);

            $unsubscribe = URL::secure(URL::route('emailing.unsubscribe', ['token' => str_repeat('x', 64)], false));
            $html = preg_replace('~<p\b[^>]*>\s*\[\[body\]\]\s*</p>~', '{{{body}}}', $html);
            $html = strtr($html, [
                e($unsubscribe) => '{{unsubscribe_url}}',
                $unsubscribe => '{{unsubscribe_url}}',
                'https://button.invalid/' => '{{button_url}}',
                '[[contact_email]]' => '{{contact_email}}',
                '[[heading]]' => '{{heading}}',
                '[[subject]]' => '{{subject}}',
                '[[button_label]]' => '{{button_label}}',
                e(config('mail.from.name')) => '{{from_name}}',
                config('mail.from.address') => '{{from_address}}',
            ]);

            $layouts[] = ['key' => $key, 'name' => $name, 'html' => $html];
        }

        return $layouts;
    }

    private function defaultLayout(): string
    {
        return <<<'HTML'
<!doctype html>
<html lang="sk"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>{{subject}}</title></head>
<body style="margin:0;background:#f1f5f9;font-family:Arial,sans-serif;color:#163047">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0"><tr><td align="center" style="padding:32px 12px">
<table role="presentation" width="600" style="max-width:600px;width:100%;background:white;border-radius:16px;overflow:hidden" cellpadding="0" cellspacing="0">
{{#unsubscribe_url}}<tr><td align="right" style="padding:12px 32px;background:#f8fafc;font-size:12px"><a href="{{unsubscribe_url}}" style="color:#64748b">Zrušiť odber</a></td></tr>{{/unsubscribe_url}}
<tr><td style="padding:28px 32px;background:#113c42;color:white;font-weight:bold;letter-spacing:2px">ZÁSTAVY &amp; VLAJKY</td></tr>
<tr><td style="padding:36px 32px">
<p style="color:#628078;font-size:13px">PONUKA PRE NAŠICH ZÁKAZNÍKOV</p>
<h1 style="font-size:30px;line-height:1.2">{{heading}}</h1>
{{{content}}}
</td></tr>
<tr><td style="padding:24px 32px;background:#f8fafc;color:#64748b;font-size:12px;line-height:1.7">{{from_name}}<br>{{from_address}}<br>
{{#unsubscribe_url}}<a href="{{unsubscribe_url}}" style="color:#64748b">Odhlásiť sa z emailových ponúk</a>{{/unsubscribe_url}}{{^unsubscribe_url}}Náhľad / test – odhlásenie bude dostupné v kampani.{{/unsubscribe_url}}
</td></tr></table></td></tr></table></body></html>
HTML;
    }

    private function date(mixed $value): ?string
    {
        return $value ? Carbon::parse($value)->toIso8601String() : null;
    }
}
