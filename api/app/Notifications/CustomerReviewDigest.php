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
 */
class CustomerReviewDigest extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * @param  array<int, array{id: int, name: string, url: string, score: int|null, issues: array, applied: array}>  $records
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
            ->subject(sprintf(
                'Kontrola zákazníkov — %d opravených, %d na pozretie',
                count($fixed),
                count($open),
            ))
            ->greeting('Dobrý deň,')
            ->line(sprintf(
                'Post-kontrola prešla %d zákazníkov. Nižšie je, čo z toho vyšlo.',
                $this->total,
            ));

        if ($fixed !== []) {
            $mail->line('**Automaticky opravené** (formát, prázdne hodnoty, doplnené daňové čísla z registra):');

            foreach (array_slice($fixed, 0, $max) as $record) {
                foreach ($record['applied'] as $change) {
                    $mail->line(sprintf(
                        '• %s — %s: „%s" → „%s"',
                        $record['name'],
                        $this->label($change['field'] ?? ''),
                        $this->show($change['from'] ?? null),
                        $this->show($change['to'] ?? null),
                    ));
                }
            }

            if (count($fixed) > $max) {
                $mail->line(sprintf('…a ďalších %d zákazníkov.', count($fixed) - $max));
            }
        }

        if ($open !== []) {
            $mail->line('**Na pozretie** — tieto zmeny by menili význam údaja, tak ich nechávame na vás:');

            foreach (array_slice($open, 0, $max) as $record) {
                $first = $record['issues'][0] ?? null;

                $mail->line(sprintf(
                    '• %s (skóre %s) — %s',
                    $record['name'],
                    $record['score'] ?? '?',
                    $first === null ? '' : $first['message'],
                ));
            }

            if (count($open) > $max) {
                $mail->line(sprintf('…a ďalších %d zákazníkov.', count($open) - $max));
            }

            $mail->action('Otvoriť zoznam zákazníkov', $this->listUrl());
        }

        return $mail->line('Opravy sa dajú vrátiť ručne — pôvodná hodnota je v tomto e-maile aj v detaile zákazníka.');
    }

    private function listUrl(): string
    {
        return rtrim((string) env('FRONTEND_URL', config('app.url')), '/').'/zakaznici?review=open';
    }

    private function label(string $field): string
    {
        return [
            'name' => 'Kontaktná osoba',
            'company' => 'Názov firmy',
            'email' => 'E-mail',
            'phone' => 'Telefón',
            'street' => 'Ulica',
            'postcode' => 'PSČ',
            'city' => 'Mesto',
            'ico' => 'IČO',
            'dic' => 'DIČ',
            'ic_dic' => 'IČ DPH',
        ][$field] ?? $field;
    }

    private function show(?string $value): string
    {
        $value = trim((string) $value);

        return $value === '' ? '(prázdne)' : $value;
    }
}
