<?php

namespace App\Services\Account;

use App\Enums\CustomerType;
use App\Enums\MailingTrigger;
use App\Enums\OrderStatus;
use App\Jobs\ReportMailingEvent;
use App\Jobs\SyncMailingContact;
use App\Models\Customer;
use App\Models\Order;
use App\Models\Shipping;
use App\Support\OrderThread;
use Carbon\Carbon;

/**
 * Emailing (kampane a automatizácie) beží v Accounte – zastavy len hlásia
 * udalosti objednávok s premennými do textu a udržiavajú adresár zákazníkov
 * (štítky: objednal, typ zákazníka, má účet).
 *
 * Kľúč udalosti je `order:{uuid}` (pri zásielke `order:{uuid}:shipping:{id}`),
 * takže opakované hlásenie nič nezdvojí. Referencia je tá istá ako pri
 * e-mailoch objednávky (OrderMailer::ref) – odpoveď vo vlákne v Accounte tak
 * nájde potvrdenie objednávky a zacituje ho.
 */
class AccountMailing
{
    public function event(MailingTrigger $event, Order $order, ?Shipping $shipping = null, ?Carbon $at = null): void
    {
        $email = mb_strtolower(trim($order->routeNotificationForMail() ?? ''));

        if ($email === '' || ! app(AccountClient::class)->mailingEnabled()) {
            return;
        }

        ReportMailingEvent::dispatch([
            'event' => $event->value,
            'email' => $email,
            'name' => $order->billing->name ?? null,
            'ref' => OrderMailer::ref($order),
            'dedupe_key' => self::dedupeKey($order, $shipping),
            'occurred_at' => ($at ?? now())->toIso8601String(),
            'variables' => [
                'cislo_objednavky' => $order->serial_number,
                'firma' => $order->billing->company ?: ($order->billing->name ?? null),
                'odkaz_objednavky' => $order->publicUrl(),
            ],
            'thread_id' => OrderThread::id($order),
        ], cancel: $event === MailingTrigger::OrderCancelled ? $order->uuid : null)->afterCommit();
    }

    /** Objednávka stornovaná alebo zmazaná: čakajúce maily k nej sa neodošlú. */
    public function cancel(Order $order): void
    {
        if (! app(AccountClient::class)->mailingEnabled()) {
            return;
        }

        ReportMailingEvent::dispatch(null, $order->uuid)->afterCommit();
    }

    /**
     * Adresu treba v adresári Accountu prepočítať (zákazník vznikol, zmenil
     * sa, objednal, bol zmazaný). Stav poskladá až job – pozri contact().
     */
    public function syncContact(?string $email): void
    {
        $email = mb_strtolower(trim((string) $email));

        if ($email === '' || ! app(AccountClient::class)->mailingEnabled()) {
            return;
        }

        SyncMailingContact::dispatch($email)->afterCommit();
    }

    /**
     * Kontakt pre adresár v Accounte zo všetkých zákazníkov s touto adresou,
     * alebo null, ak adresa nepatrí žiadnemu zákazníkovi (kontakt sa zmaže).
     *
     * Zákazníci dávajú pri objednávke súhlas s ponukami – kontakt ide so
     * súhlasom od založenia prvého zákazníka s touto adresou.
     *
     * @return array<string, mixed>|null
     */
    public function contact(string $email): ?array
    {
        $email = mb_strtolower(trim($email));
        // Používateľa má po importe zo starého e-shopu každý zákazník – „účet“
        // je až ten, kto sa do e-shopu naozaj prihlásil.
        $customers = Customer::query()->whereRaw('LOWER(email) = ?', [$email])
            ->withCount(['users' => fn ($q) => $q->whereNotNull('last_login_at')])
            ->oldest('id')->get();

        if ($customers->isEmpty()) {
            return null;
        }

        $ordered = Order::query()->whereIn('customer_id', $customers->modelKeys())
            ->whereNotIn('status', [OrderStatus::Draft->value, OrderStatus::Cancelled->value])
            ->exists();
        // Údaje do textu z naposledy upraveného zákazníka.
        $latest = $customers->sortByDesc(fn (Customer $c) => $c->getRawOriginal('updated_at'))->first();
        $first = $customers->first();

        return [
            'email' => $email,
            'name' => trim((string) $latest->name) ?: null,
            'tags' => array_values(array_unique(array_filter([
                $ordered ? 'customer' : null,
                ...$customers->map(fn (Customer $c) => $c->type?->value)->all(),
                $customers->sum('users_count') > 0 ? 'account' : null,
            ]))),
            'attributes' => array_filter([
                'meno' => trim((string) $latest->name) ?: null,
                'firma' => trim((string) $latest->company) ?: null,
            ]),
            'permission_note' => 'Zákazník Zástav – súhlas pri objednávke',
            'permission_at' => Carbon::parse($first->getRawOriginal('created_at') ?? now())->toIso8601String(),
        ];
    }

    /** Štítky pre výber adresátov v Accounte. */
    public function tags(): array
    {
        return [
            ['key' => 'customer', 'label' => 'Objednal', 'description' => 'Má aspoň jednu objednávku (okrem konceptu a storna).'],
            ['key' => CustomerType::Municipality->value, 'label' => 'Obec', 'description' => 'Obec alebo mesto.'],
            ['key' => CustomerType::School->value, 'label' => 'Škola'],
            ['key' => CustomerType::Company->value, 'label' => 'Firma / organizácia', 'description' => 'Firma, farnosť, združenie a iné organizácie s IČO.'],
            ['key' => CustomerType::Person->value, 'label' => 'Súkromná osoba'],
            ['key' => 'account', 'label' => 'Prihlásil sa do e-shopu', 'description' => 'Zákazník sa aspoň raz prihlásil do svojho účtu.'],
        ];
    }

    /** Udalosti pre výber spúšťača v Accounte. */
    public function triggers(): array
    {
        return array_map(fn (MailingTrigger $trigger) => [
            'key' => $trigger->value,
            'label' => $trigger->label(),
            'short_label' => $trigger->shortLabel(),
            'variables' => MailingTrigger::VARIABLES,
            'quote_kind' => $trigger->quoteKind(),
        ], MailingTrigger::cases());
    }

    public static function dedupeKey(Order $order, ?Shipping $shipping = null): string
    {
        return 'order:'.$order->uuid.($shipping ? ':shipping:'.$shipping->id : '');
    }
}
