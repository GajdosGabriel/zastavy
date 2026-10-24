<?php

namespace App\Services\Account;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Klient pre Account – zatiaľ len komunikácia so zákazníkmi.
 *
 * Predloha je v Accounte v `klient-pre-projekty/`. Firemné údaje
 * (organizácie, predplatné) zastavy z Accountu nečítajú.
 */
class AccountClient
{
    public function enabled(): bool
    {
        return filled(config('account.token'));
    }

    /** Posielať e-maily zákazníkom cez Account? */
    public function messagingEnabled(): bool
    {
        return $this->enabled() && (bool) config('account.messaging');
    }

    /**
     * Odošle e-mail cez schránku zastáv v Accounte.
     *
     * Volá sa z fronty, výpadok Accountu teda vyrieši opakovanie. Blokovaná
     * adresa vyhodí AccountMessageRejected – tú opakovať nemá zmysel.
     *
     * @param  array<string, mixed>  $message
     * @return array{conversation: array<string, mixed>, message: array<string, mixed>}
     */
    public function sendMessage(array $message): array
    {
        $this->assertWritesAllowed();

        $response = $this->request()->post('/api/v1/messages', $message);

        if ($response->status() === 422 && $response->json('reason')) {
            throw new AccountMessageRejected($response->json('reason'), (string) $response->json('message'));
        }

        return $response->throw()->json('data');
    }

    /**
     * Konverzácie k objednávke (aj s e-mailami, ktoré prišli do Inboxu).
     *
     * @return array<int, array<string, mixed>>
     */
    public function conversations(string $refType, string|int $refId): array
    {
        if (! $this->enabled()) {
            return [];
        }

        try {
            return $this->request()
                ->get('/api/v1/conversations', ['ref_type' => $refType, 'ref_id' => (string) $refId])
                ->throw()
                ->json('data');
        } catch (\Throwable $e) {
            Log::warning('Account: konverzacie sa nepodarilo nacitat', ['error' => $e->getMessage()]);

            return [];
        }
    }

    /** Odhlásenie z e-mailov – Account na adresu prestane posielať. Najlepšia snaha. */
    public function suppress(string $email, string $reason = 'unsubscribed'): void
    {
        if (! $this->messagingEnabled()) {
            return;
        }

        try {
            $this->assertWritesAllowed();
            $this->request()->post('/api/v1/suppressions', ['email' => $email, 'reason' => $reason])->throw();
        } catch (\Throwable $e) {
            Log::warning('Account: odhlasenie sa nepodarilo nahlasit', ['email' => $email, 'error' => $e->getMessage()]);
        }
    }

    /** Kampane (emailing) bežia v Accounte? */
    public function mailingEnabled(): bool
    {
        return $this->enabled() && (bool) config('account.mailing');
    }

    /**
     * Udalosť pre automatizácie v Accounte. Volá sa z fronty (ReportMailingEvent).
     *
     * @param  array<string, mixed>  $event  pozri POST /api/v1/mailing/events
     */
    public function mailingEvent(array $event): int
    {
        $this->assertWritesAllowed();

        return (int) $this->request()->post('/api/v1/mailing/events', $event)->throw()->json('data.queued');
    }

    /**
     * Vec sa zrušila – čakajúce maily k nej sa neodošlú.
     *
     * @param  array<int, string>  $except  udalosti, ktorých maily ostanú
     */
    public function cancelMailing(string $refType, string $refId, array $except = []): int
    {
        $this->assertWritesAllowed();

        return (int) $this->request()->post('/api/v1/mailing/events/cancel', [
            'ref' => ['type' => $refType, 'id' => $refId],
            'except' => $except,
        ])->throw()->json('data.skipped');
    }

    /** @param  array<int, array<string, mixed>>  $triggers */
    public function registerMailingTriggers(array $triggers): void
    {
        $this->assertWritesAllowed();

        $this->request()->put('/api/v1/mailing/triggers', ['triggers' => $triggers])->throw();
    }

    /**
     * Štítky kontaktov (segmenty) pre výber adresátov kampane v Accounte.
     *
     * @param  array<int, array{key: string, label: string, description?: string}>  $tags
     */
    public function registerMailingTags(array $tags): void
    {
        $this->assertWritesAllowed();

        $this->request()->put('/api/v1/mailing/tags', ['tags' => $tags])->throw();
    }

    /**
     * Adresár v Accounte (max. 1000 naraz). Štítky a atribúty nahradia
     * doterajšie; súhlas sa raz udelí a neodoberie, odhláseného nevráti.
     *
     * @param  array<int, array<string, mixed>>  $contacts
     * @return array{added: int, updated: int}
     */
    public function upsertMailingContacts(array $contacts): array
    {
        $this->assertWritesAllowed();

        return $this->request()->post('/api/v1/mailing/contacts', ['contacts' => $contacts])->throw()->json('data');
    }

    /**
     * Adresa už nepatrí žiadnemu zákazníkovi – Account zmaže kontakt.
     *
     * @param  array<int, string>  $emails
     */
    public function removeMailingContacts(array $emails): int
    {
        $this->assertWritesAllowed();

        return (int) $this->request()->post('/api/v1/mailing/contacts/remove', ['emails' => $emails])->throw()->json('data.removed');
    }

    /**
     * Poistka: mimo produkcie sa posiela len do lokálnej inštancie Accountu
     * (localhost, *.local, *.test), inak len s ACCOUNT_ALLOW_REMOTE_WRITES.
     */
    public function assertWritesAllowed(): void
    {
        if (app()->isProduction() || config('account.allow_remote_writes')) {
            return;
        }

        $host = strtolower((string) parse_url((string) config('account.url'), PHP_URL_HOST));

        if (in_array($host, ['localhost', '127.0.0.1', '::1', ''], true) || preg_match('/\.(local|test|localhost)$/', $host)) {
            return;
        }

        throw new \RuntimeException('Odosielanie cez vzdialený Account je mimo produkcie vypnuté (ACCOUNT_ALLOW_REMOTE_WRITES).');
    }

    protected function request(): PendingRequest
    {
        return Http::withToken(config('account.token'))
            ->acceptJson()
            ->timeout(config('account.timeout'))
            ->baseUrl(config('account.url'));
    }
}
