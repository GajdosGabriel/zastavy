<?php

namespace App\Services;

use App\Enums\MailingTrigger;
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
        $contact = DB::table('mailing_contacts')->where('email', $email)->whereNull('unsubscribed_at')->whereNull('bounced_at')->first();
        if (! $contact) return;
        $at ??= now();
        DB::transaction(function () use ($event, $order, $shipping, $contact, $at) {
            $campaigns = DB::table('mailing_campaigns')->where('trigger_event', $event->value)
                ->where('status', 'queued')->orderBy('id')->lockForUpdate()->get();
            foreach ($campaigns as $campaign) {
                DB::table('mailing_deliveries')->insertOrIgnore([
                    'campaign_id' => $campaign->id, 'contact_id' => $contact->id,
                    'event_order_id' => $order->id, 'event_shipping_id' => $shipping?->id ?? 0,
                    'due_at' => $at->copy()->addHours($campaign->delay_hours),
                    'status' => 'pending', 'created_at' => now(), 'updated_at' => now(),
                ]);
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

    public function html(object $campaign, ?object $contact = null): string
    {
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

    public function send(object $campaign, object $contact, bool $test = false, ?object $delivery = null): void
    {
        $html = $this->html($campaign, $test ? null : $contact);
        if (! $test && $delivery) {
            $html = app(EmailingMeasurement::class)->decorate($html, $campaign, $delivery, $this->unsubscribeUrl($contact));
        }
        if ($this->dryRun()) {
            // Všetko vrátane vykreslenia a merania prebehlo; len sa nevolá poštový server.
            \Illuminate\Support\Facades\Log::info('Emailing dry-run: email sa neodoslal.', [
                'to' => $contact->email, 'subject' => ($test ? '[TEST] ' : '').$campaign->subject,
                'campaign_id' => $campaign->id ?? null, 'delivery_id' => $delivery->id ?? null, 'test' => $test,
            ]);

            return;
        }
        Mail::html($html, function ($message) use ($campaign, $contact, $test) {
            $message->to($contact->email)->subject(($test ? '[TEST] ' : '').$campaign->subject);
            if (! $test) {
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
            $contact = DB::table('mailing_contacts')->find($delivery->contact_id);
            $campaign = DB::table('mailing_campaigns')->find($delivery->campaign_id);
            $result = ['status' => 'skipped'];
            $order = $delivery->event_order_id ? \App\Models\Order::find($delivery->event_order_id) : null;
            $orderEligible = ! $delivery->event_order_id || ($order && $order->status !== \App\Enums\OrderStatus::Cancelled
                && mb_strtolower(trim($order->routeNotificationForMail() ?? '')) === $contact?->email);
            $coupon = $campaign->coupon_id ? \App\Models\Coupon::find($campaign->coupon_id) : null;
            $couponEligible = ! $campaign->trigger_event || ! $campaign->coupon_code || ($coupon && $coupon->active
                && (! $coupon->valid_to || ! $coupon->valid_to->copy()->endOfDay()->isPast()));
            if ($contact && ! $contact->unsubscribed_at && ! $contact->bounced_at && $campaign->status === 'queued' && $orderEligible && $couponEligible) {
                try {
                    $this->send($campaign, $contact, false, $delivery);
                    $result = ['status' => 'sent', 'sent_at' => now()];
                    if ($contact->soft_bounces) {
                        DB::table('mailing_contacts')->where('id', $contact->id)->update(['soft_bounces' => 0]);
                    }
                } catch (\Throwable $e) {
                    report($e);
                    $bounce = app(EmailBounceClassifier::class)->classify($e->getMessage());
                    $result = ['status' => 'failed', 'error' => 'Odoslanie zlyhalo. Podrobnosti sú v serverovom denníku.'];
                    if ($bounce['type'] !== EmailBounceClassifier::NONE) {
                        $result = ['status' => $this->recordBounce($contact->email, $bounce['type'], $bounce['reason']) ? 'bounced' : 'failed', 'error' => $bounce['reason']];
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
