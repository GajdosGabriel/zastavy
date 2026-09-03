<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * „Pri kontrole zákazníkov sme niečo opravili a na niečo sa pýtame."
 *
 * Súhrn za celý beh príkazu, nie e-mail za každého zákazníka. Pri prvom
 * prechode existujúcou tabuľkou by druhá možnosť znamenala stovky správ
 * a admin by ich prestal čítať skôr, než by prišla tá dôležitá.
 *
 * E-mail má dve časti a je dôležité, aby sa nepomiešali:
 *
 *   OPRAVENÉ  — čo automat už zmenil. Toto je oznam, nie otázka; admin má
 *               právo vedieť, že sa mu v tabuľke hýbali fakturačné údaje,
 *               a vidieť pôvodnú hodnotu, keby to bolo zle.
 *   NA POZRETIE — čo sa opraviť nesmelo. Toto je otázka a odpovedá sa na ňu
 *               v detaile zákazníka, kde na návrhu čaká tlačidlo.
 *
 * Texty sú v `lang/*` a nálezy sa prekladajú tu, pri odosielaní — v databáze
 * ležia ako kľúč a parametre. Beh príkazu nemá žiadny request, z ktorého by
 * sa dal jazyk odvodiť, takže sa použije jazyk príjemcu, ak ho má nastavený
 * (User implementuje HasLocalePreference a Laravel to rešpektuje sám).
 */
class CustomerReviewDigest extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * @param  array<int, array{id: int, name: string, score: int|null, issues: array, applied: array}>  $records
     * @param  int  $total  koľko zákazníkov beh dokopy posúdil
     */
    public function __construct(
        protected array $records,
        protected int $total,
    ) {
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $fixed = array_values(array_filter($this->records, fn (array $r) => $r['applied'] !== []));
        $open = array_values(array_filter($this->records, fn (array $r) => $r['issues'] !== []));
        $max = (int) config('customer_review.digest.max_records', 15);

        $mail = (new MailMessage())
            ->subject(__('customer_review.mail.subject', [
                'fixed' => count($fixed),
                'open' => count($open),
            ]))
            ->greeting(__('customer_review.mail.greeting'))
            ->line(__('customer_review.mail.intro', ['total' => $this->total]));

        if ($fixed !== []) {
            $mail->line(__('customer_review.mail.fixed_heading'));

            foreach (array_slice($fixed, 0, $max) as $record) {
                foreach ($record['applied'] as $change) {
                    $mail->line(__('customer_review.mail.fixed_line', [
                        'customer' => $record['name'],
                        'field' => $this->fieldLabel($change['field'] ?? ''),
                        'from' => $this->show($change['from'] ?? null),
                        'to' => $this->show($change['to'] ?? null),
                    ]));
                }
            }

            $this->more($mail, count($fixed), $max);
        }

        if ($open !== []) {
            $mail->line(__('customer_review.mail.open_heading'));

            foreach (array_slice($open, 0, $max) as $record) {
                $first = $record['issues'][0] ?? null;

                $mail->line(__('customer_review.mail.open_line', [
                    'customer' => $record['name'],
                    'score' => $record['score'] ?? '?',
                    'message' => $first === null ? '' : $this->message($first),
                ]));
            }

            $this->more($mail, count($open), $max);

            $mail->action(__('customer_review.mail.action'), $this->listUrl());
        }

        return $mail->line(__('customer_review.mail.outro'));
    }

    private function more(MailMessage $mail, int $count, int $max): void
    {
        if ($count > $max) {
            $mail->line(__('customer_review.mail.more', ['count' => $count - $max]));
        }
    }

    /** Nález od pravidiel a registra sa prekladá; nález od AI je hotová veta. */
    private function message(array $issue): string
    {
        $key = $issue['key'] ?? null;

        return $key === null
            ? (string) ($issue['message'] ?? '')
            : __($key, (array) ($issue['params'] ?? []));
    }

    private function listUrl(): string
    {
        return rtrim((string) env('FRONTEND_URL', config('app.url')), '/').'/zakaznici?review=open';
    }

    private function fieldLabel(string $field): string
    {
        return $field === '' ? '' : __('customer_review.fields.'.$field);
    }

    private function show(?string $value): string
    {
        $value = trim((string) $value);

        return $value === '' ? __('customer_review.mail.empty') : $value;
    }
}
