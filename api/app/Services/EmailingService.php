<?php

namespace App\Services;

use App\Enums\MailingTrigger;
use App\Support\OrderThread;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;

class EmailingService
{
    public function orderCreated(\App\Models\Order $order): void
    {
        $this->trigger(MailingTrigger::OrderCreated, $order, null, $order->created_at);
    }

    /**
     * Zaradí odoslanie všetkým aktívnym kampaniam viazaným na danú udalosť.
     * Oneskorenie kampane sa počíta od $at (predvolene od tejto chvíle); opakované volanie
     * pre tú istú objednávku/zásielku je bezpečné (deduplikuje sa v DB).
     */
    public function trigger(MailingTrigger $event, \App\Models\Order $order, ?\App\Models\Shipping $shipping = null, ?\Carbon\Carbon $at = null): void
    {
        if (! \Illuminate\Support\Facades\Schema::hasColumn('mailing_deliveries', 'event_shipping_id')) return;
        $email = mb_strtolower(trim($order->routeNotificationForMail() ?? ''));
        if ($email === '') return;
        $contact = DB::table('mailing_contacts')->where('email', $email)->whereNull('unsubscribed_at')->whereNull('bounced_at')->first();
        $at ??= now();
        DB::transaction(function () use ($event, $order, $shipping, $contact, $email, $at) {
            $campaigns = DB::table('mailing_campaigns')->where('trigger_event', $event->value)
                ->where('status', 'queued')->orderBy('id')->lockForUpdate()->get();
            foreach ($campaigns as $campaign) {
                $row = [
                    'campaign_id' => $campaign->id,
                    'event_order_id' => $order->id, 'event_shipping_id' => $shipping?->id ?? 0,
                    'due_at' => $at->copy()->addHours($campaign->delay_hours),
                    'status' => 'pending', 'created_at' => now(), 'updated_at' => now(),
                ];
                if ($campaign->reply_to_order ?? false) {
                    // Servisná odpoveď ide každému zákazníkovi objednávky, aj mimo adresára príjemcov.
                    // Unikátny index s NULL kontaktom nededuplikuje; zámok kampane vyššie udalosti radí za sebou.
                    $exists = DB::table('mailing_deliveries')->where('campaign_id', $campaign->id)
                        ->where('event_order_id', $row['event_order_id'])->where('event_shipping_id', $row['event_shipping_id'])->exists();
                    if (! $exists) {
                        DB::table('mailing_deliveries')->insert($row + ['contact_id' => null, 'email' => $email]);
                    }
                } elseif ($contact) {
                    DB::table('mailing_deliveries')->insertOrIgnore($row + ['contact_id' => $contact->id]);
                }
            }
        });
    }

    public function ready(): bool
    {
        return config('emailing.enabled') && ($this->dryRun() || in_array(config('mail.default'), ['smtp', 'sendmail', 'ses', 'postmark', 'resend']));
    }

    public function dryRun(): bool
    {
        return (bool) config('emailing.dry_run');
    }

    /**
     * Telo ponuky pre e-mail. Text z HTML editora prejde bielou listinou značiek;
     * staršie kampane a vstavané šablóny sú čistý text s odsekmi oddelenými prázdnym riadkom.
     */
    public static function bodyHtml(?string $body, string $paragraphStyle): string
    {
        $body = trim((string) $body);
        $html = preg_match('/<(p|br|h2|h3|ul|ol|li|strong|b|em|i|u|a)\b/i', $body)
            ? HtmlSanitizer::sanitize($body)
            : collect(preg_split("/\r?\n\s*\r?\n/", $body))->map(fn ($p) => '<p>'.nl2br(e($p)).'</p>')->implode("\n");

        // Sanitizer aj vetva vyššie vracajú <p> bez atribútov; e-mailoví klienti potrebujú štýl priamo v značke.
        return str_replace('<p>', '<p style="'.$paragraphStyle.'">', $html);
    }

    /**
     * Text odpovede vo vlákne objednávky; značky v zložených zátvorkách sa nahradia údajmi objednávky.
     * Bez objednávky (náhľad v prázdnom systéme) značky zostanú viditeľné.
     */
    public static function orderReplyBody(?string $body, ?\App\Models\Order $order): string
    {
        $html = self::bodyHtml($body, 'margin:0 0 12px');
        if ($order) {
            $html = strtr($html, [
                '{cislo_objednavky}' => e($order->serial_number),
                '{firma}' => e($order->billing->company),
                '{odkaz_objednavky}' => e($order->publicUrl()),
            ]);
        }

        return self::autolink($html);
    }

    /** Webové adresy napísané ako obyčajný text zmení na odkazy; existujúce odkazy nechá tak. */
    private static function autolink(string $html): string
    {
        $inLink = false;

        return implode('', array_map(function ($part) use (&$inLink) {
            if (str_starts_with($part, '<')) {
                $inLink = preg_match('~^<a\b~i', $part) ? true : (preg_match('~^</a\b~i', $part) ? false : $inLink);

                return $part;
            }

            // Interpunkcia na konci vety do adresy nepatrí.
            return $inLink ? $part : preg_replace('~\bhttps?://[^\s<>"\']*[^\s<>"\'.,;:!?)]~iu', '<a href="$0" style="color:#1155cc">$0</a>', $part);
        }, preg_split('~(<[^>]+>)~', $html, -1, PREG_SPLIT_DELIM_CAPTURE)));
    }

    /**
     * @param  \Carbon\Carbon|null  $eventAt  kedy udalosť nastala (čas pôvodnej správy v citáte)
     */
    public function html(object $campaign, ?object $contact = null, ?\App\Models\Order $order = null, ?\App\Models\Shipping $shipping = null, ?\Carbon\Carbon $eventAt = null): string
    {
        if ($campaign->reply_to_order ?? false) {
            $quote = [];
            $event = MailingTrigger::tryFrom($campaign->trigger_event ?? '');
            if ($order && $event && ($campaign->quote_event ?? true)) {
                // Citát je systémový e-mail danej udalosti (potvrdenie, expedícia, storno…).
                $mail = $event->officialNotification($order, $shipping)->toMail($order);
                $original = view($mail->view, $mail->viewData)->render();
                preg_match('~<style[^>]*>(.*?)</style>~is', $original, $style);
                preg_match('~<body[^>]*>(.*)</body>~is', $original, $body);
                $quote = [
                    // Pravidlo pre body by prefarbilo aj samotnú odpoveď nad citátom.
                    'quoteStyle' => preg_replace('~(?<![.#\w-])body\s*\{[^}]*\}~i', '', $style[1] ?? ''),
                    'quoteHtml' => $body[1] ?? $original,
                    'quotedAt' => $eventAt ?? ($event === MailingTrigger::OrderCreated ? $order->created_at : now()),
                ];
            }

            return view('emails.campaign-order-reply', ['campaign' => $campaign, 'order' => $order] + $quote)->render();
        }

        $view = match ($campaign->layout ?? 'default') {
            'spisor' => 'emails.campaign-spisor',
            'flags-catalog' => 'emails.campaign-flags-catalog',
            default => 'emails.campaign',
        };

        $productImages = [];
        if (($campaign->layout ?? 'default') === 'flags-catalog') {
            foreach (\App\Models\Product::with('images')->whereIn('id', [1, 2, 5, 6])->get() as $product) {
                $image = $product->images->first();
                if ($image) {
                    $productImages[$product->id] = URL::route('images.show', ['image' => $image->id]);
                }
            }
        }

        return view($view, [
            'productImages' => $productImages,
            'campaign' => $campaign,
            'contact' => $contact,
            'unsubscribe' => $contact ? $this->unsubscribeUrl($contact) : null,
        ])->render();
    }

    public function send(object $campaign, object $contact, bool $test = false, ?object $delivery = null, ?\App\Models\Order $order = null): void
    {
        // Odpoveď vo vlákne objednávky je servisná správa: bez odhlásenia a bez merania.
        $reply = ($campaign->reply_to_order ?? false) && $order;
        $shipping = ($delivery->event_shipping_id ?? 0) ? \App\Models\Shipping::find($delivery->event_shipping_id) : null;
        // Udalosť nastala o oneskorenie kampane skôr, než je termín odoslania.
        $eventAt = ($delivery->due_at ?? null) ? \Carbon\Carbon::parse($delivery->due_at)->subHours($campaign->delay_hours ?? 0) : null;
        $html = $this->html($campaign, $test ? null : $contact, $order, $shipping, $eventAt);
        if (! $test && $delivery && ! $reply) {
            $html = app(EmailingMeasurement::class)->decorate($html, $campaign, $delivery, $this->unsubscribeUrl($contact));
        }
        $subject = ($test ? '[TEST] ' : '').($reply ? OrderThread::replySubject($order) : $campaign->subject);
        if ($this->dryRun()) {
            // Všetko vrátane vykreslenia a merania prebehlo; len sa nevolá poštový server.
            \Illuminate\Support\Facades\Log::info('Emailing dry-run: email sa neodoslal.', [
                'to' => $contact->email, 'subject' => $subject,
                'campaign_id' => $campaign->id ?? null, 'delivery_id' => $delivery->id ?? null, 'test' => $test,
            ]);

            return;
        }
        Mail::html($html, function ($message) use ($contact, $test, $reply, $order, $subject) {
            $message->to($contact->email)->subject($subject);
            if ($reply) {
                // Rovnaký odosielateľ ako potvrdenie objednávky, inak klient správy nespojí.
                $message->from(OrderThread::FROM, OrderThread::FROM_NAME)->replyTo(OrderThread::FROM, OrderThread::FROM_NAME);
                if (! $test) {
                    OrderThread::reply($message->getSymfonyMessage(), $order);
                }
            } elseif (! $test) {
                $url = $this->unsubscribeUrl($contact);
                $headers = $message->getSymfonyMessage()->getHeaders();
                $headers->addTextHeader('List-Unsubscribe', '<'.$url.'>');
                $headers->addTextHeader('List-Unsubscribe-Post', 'List-Unsubscribe=One-Click');
            }
        });
    }

    private function unsubscribeUrl(object $contact): string
    {
        // One-click unsubscribe requires HTTPS, including behind an HTTP proxy.
        return URL::secure(URL::route('emailing.unsubscribe', ['token' => $contact->token], false));
    }

    public const SOFT_BOUNCE_LIMIT = 3;

    /**
     * Zaznamená nedoručiteľnosť adresy (z SMTP odpovede alebo z doručenej správy o nedoručení).
     * Hard bounce vylúči kontakt hneď, soft až po opakovanom zlyhaní za sebou.
     * Vráti true, ak je kontakt po zápise vylúčený.
     */
    public function recordBounce(string $email, string $type, string $reason = ''): bool
    {
        $email = mb_strtolower(trim($email));
        $reason = mb_substr($reason, 0, 255);

        return DB::transaction(function () use ($email, $type, $reason) {
            $contact = DB::table('mailing_contacts')->where('email', $email)->lockForUpdate()->first();
            if (! $contact) {
                return false;
            }
            if ($contact->bounced_at) {
                return true;
            }
            $soft = $contact->soft_bounces + ($type === EmailBounceClassifier::SOFT ? 1 : 0);
            $suppress = $type === EmailBounceClassifier::HARD || $soft >= self::SOFT_BOUNCE_LIMIT;
            DB::table('mailing_contacts')->where('id', $contact->id)->update([
                'soft_bounces' => $soft, 'bounce_reason' => $reason, 'updated_at' => now(),
            ] + ($suppress ? ['bounced_at' => now(), 'bounce_type' => $type === EmailBounceClassifier::HARD ? 'hard' : 'soft_limit'] : []));
            if ($suppress) {
                // Čakajúce správy tejto adresy sa už nemajú odosielať.
                DB::table('mailing_deliveries')->where('contact_id', $contact->id)->where('status', 'pending')
                    ->update(['status' => 'skipped', 'updated_at' => now()]);
            }

            return $suppress;
        });
    }

    public function runBatch(): int
    {
        if (! $this->ready()) {
            return 0;
        }
        $count = 0;
        // A DB row is the global mutex, including across multiple scheduler hosts.
        // SMTP attempts are never automatically retried: interrupted attempts remain visible.
        for ($i = 0; $i < config('emailing.batch_size'); $i++) {
            $delivery = DB::transaction(function () {
                $mutex = DB::table('mailing_campaigns')->orderBy('id')->lockForUpdate()->first();
                if (! $mutex) {
                    return null;
                }
                $used = DB::table('mailing_deliveries')->where('attempted_at', '>=', now()->subHour())->count();
                if ($used >= config('emailing.hourly_limit')) {
                    return null;
                }
                $delivery = DB::table('mailing_deliveries as d')
                    ->join('mailing_campaigns as c', 'c.id', '=', 'd.campaign_id')
                    ->where('d.status', 'pending')->where('c.status', 'queued')
                    ->where(fn ($q) => $q->whereNull('d.due_at')->orWhere('d.due_at', '<=', now()))
                    ->where('c.scheduled_at', '<=', now())->orderBy('d.id')->select('d.*')->first();
                if ($delivery) {
                    $claimed = DB::table('mailing_deliveries')->where('id', $delivery->id)->where('status', 'pending')->update(['status' => 'sending', 'attempted_at' => now(), 'updated_at' => now()]);
                    if (! $claimed) {
                        return null;
                    }
                }

                return $delivery;
            });
            if (! $delivery) {
                break;
            }
            $contact = $delivery->contact_id ? DB::table('mailing_contacts')->find($delivery->contact_id) : null;
            $campaign = DB::table('mailing_campaigns')->find($delivery->campaign_id);
            $result = ['status' => 'skipped'];
            $order = $delivery->event_order_id ? \App\Models\Order::find($delivery->event_order_id) : null;
            $orderEmail = mb_strtolower(trim($order?->routeNotificationForMail() ?? ''));
            // Stornovaná objednávka už nič nedostane — okrem kampane viazanej práve na storno.
            $orderActive = $order && ($order->status !== \App\Enums\OrderStatus::Cancelled || $campaign->trigger_event === MailingTrigger::OrderCancelled->value);
            if (! $delivery->contact_id) {
                // Odpoveď vo vlákne: príjemca je aktuálny email objednávky. Marketingové odhlásenie
                // sa neuplatňuje, nedoručiteľná adresa áno.
                $eligible = $orderActive && $orderEmail !== ''
                    && ! DB::table('mailing_contacts')->where('email', $orderEmail)->whereNotNull('bounced_at')->exists();
                $recipient = (object) ['email' => $orderEmail, 'name' => null];
            } else {
                $orderEligible = ! $delivery->event_order_id || ($orderActive && $orderEmail === $contact?->email);
                $coupon = $campaign->coupon_id ? \App\Models\Coupon::find($campaign->coupon_id) : null;
                $couponEligible = ! $campaign->trigger_event || ! $campaign->coupon_code || ($coupon && $coupon->active
                    && (! $coupon->valid_to || ! $coupon->valid_to->copy()->endOfDay()->isPast()));
                $eligible = $contact && ! $contact->unsubscribed_at && ! $contact->bounced_at && $orderEligible && $couponEligible;
                $recipient = $contact;
            }
            if ($eligible && $campaign->status === 'queued') {
                try {
                    $this->send($campaign, $recipient, false, $delivery, $order);
                    $result = ['status' => 'sent', 'sent_at' => now()];
                    if ($contact?->soft_bounces) {
                        DB::table('mailing_contacts')->where('id', $contact->id)->update(['soft_bounces' => 0]);
                    }
                } catch (\Throwable $e) {
                    report($e);
                    $bounce = app(EmailBounceClassifier::class)->classify($e->getMessage());
                    $result = ['status' => 'failed', 'error' => 'Odoslanie zlyhalo. Podrobnosti sú v serverovom denníku.'];
                    if ($bounce['type'] !== EmailBounceClassifier::NONE) {
                        $result = ['status' => $this->recordBounce($recipient->email, $bounce['type'], $bounce['reason']) ? 'bounced' : 'failed', 'error' => $bounce['reason']];
                    }
                }
            }
            DB::table('mailing_deliveries')->where('id', $delivery->id)->update($result + ['updated_at' => now()]);
            $count++;
            if (! $campaign->trigger_event && ! DB::table('mailing_deliveries')->where('campaign_id', $campaign->id)->whereIn('status', ['pending', 'sending'])->exists()) {
                DB::table('mailing_campaigns')->where('id', $campaign->id)->where('status', 'queued')->update(['status' => 'completed', 'updated_at' => now()]);
            }
        }

        return $count;
    }
}
