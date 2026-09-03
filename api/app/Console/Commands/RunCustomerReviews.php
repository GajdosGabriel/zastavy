<?php

namespace App\Console\Commands;

use App\Models\CustomerReview;
use App\Models\User;
use App\Notifications\CustomerReviewDigest;
use App\Services\Customers\CustomerReviewService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Notification;

/**
 * Posúdi dávku zákazníkov, ktorým dobehol odklad (viď CustomerReviewService).
 *
 * Beh má časový strop: každý riadok je až jedno volanie registra a jedno
 * volanie OpenAI, teda sekundy. Zvyšok dávky sa presunie do ďalšieho behu —
 * kontrola údajov nikam nespěchá a riadky, ktoré čakajú roky, počkajú aj
 * ďalších päť minút.
 *
 * Prvý prechod existujúcou tabuľkou:
 *
 *   php artisan app:customer-reviews-run --schedule-all --dry-run
 *   php artisan app:customer-reviews-run --limit=50
 */
class RunCustomerReviews extends Command
{
    protected $signature = 'app:customer-reviews-run
        {--limit= : Koľko zákazníkov posúdiť (predvolene z configu)}
        {--customer= : Posúdiť jedného zákazníka podľa ID, hneď a bez ohľadu na odklad}
        {--schedule-all : Najprv naplánovať kontrolu všetkým zákazníkom}
        {--dry-run : Nič nemeniť ani neposielať, len vypísať, čo by beh spravil}
        {--no-mail : Posúdiť, ale neposielať súhrn adminovi}
        {--time-budget=25 : Strop behu v sekundách (0 = bez stropu)}';

    protected $description = 'Skontroluje údaje zákazníkov (pravidlá + register + AI) a pošle adminovi súhrn';

    public function handle(CustomerReviewService $service): int
    {
        if (! config('customer_review.enabled', true)) {
            $this->info('Kontrola zákazníkov je vypnutá konfiguráciou.');

            return self::SUCCESS;
        }

        if ($this->option('schedule-all')) {
            $scheduled = $service->scheduleAll();
            $this->info(sprintf('Naplánovaných na kontrolu: %d', $scheduled));
        }

        $reviews = $this->target($service);

        if ($reviews->isEmpty()) {
            $this->info('Nič splatné.');

            return self::SUCCESS;
        }

        if ($this->option('dry-run')) {
            return $this->dryRun($reviews);
        }

        $start = microtime(true);
        $timeBudget = max(0, (int) $this->option('time-budget'));

        $records = [];
        $total = 0;

        foreach ($reviews as $review) {
            if (! $service->run($review)) {
                continue;
            }

            $total++;
            $review->refresh();

            $issues = (array) ($review->issues ?? []);
            $applied = (array) ($review->applied ?? []);

            if ($issues !== [] || $applied !== []) {
                $records[] = [
                    'id' => $review->customer_id,
                    'name' => $this->customerLabel($review),
                    'score' => $review->score,
                    'issues' => $issues,
                    'applied' => $applied,
                ];
            }

            $this->line(sprintf(
                '  #%d %s — skóre %s, nálezov %d, opravených %d',
                $review->customer_id,
                $this->customerLabel($review),
                $review->score ?? '?',
                count($issues),
                count($applied),
            ));

            if ($timeBudget > 0 && microtime(true) - $start > $timeBudget) {
                $this->comment('Časový strop behu — zvyšok sa dobehne nabudúce.');
                break;
            }
        }

        $this->info(sprintf(
            'Posúdených %d, z toho s nálezom %d.',
            $total,
            count($records),
        ));

        $this->notifyAdmins($records, $total);

        return self::SUCCESS;
    }

    /** @return \Illuminate\Database\Eloquent\Collection<int, CustomerReview> */
    private function target(CustomerReviewService $service)
    {
        $customerId = $this->option('customer');

        if ($customerId === null) {
            return $service->due($this->option('limit') !== null ? max(1, (int) $this->option('limit')) : null);
        }

        // Jeden konkrétny zákazník sa posudzuje bez ohľadu na odklad —
        // je to ručné „pozri sa na tento riadok teraz".
        return CustomerReview::query()
            ->where('customer_id', (int) $customerId)
            ->with('customer')
            ->get();
    }

    private function dryRun($reviews): int
    {
        $this->comment('DRY RUN — nič sa nemení ani neposiela.');

        $rows = [];

        foreach ($reviews as $review) {
            $rows[] = [
                $review->customer_id,
                $this->customerLabel($review),
                $review->due_at?->format('d.m.Y H:i') ?? '-',
                $review->reviewed_at === null ? 'nikdy' : $review->reviewed_at->format('d.m.Y H:i'),
            ];
        }

        $this->table(['ID', 'Zákazník', 'Splatné', 'Naposledy posúdené'], $rows);
        $this->info(sprintf('Na rade je %d zákazníkov.', count($rows)));

        return self::SUCCESS;
    }

    private function notifyAdmins(array $records, int $total): void
    {
        if ($records === [] || $this->option('no-mail')) {
            return;
        }

        if (count($records) < (int) config('customer_review.digest.min_records', 1)) {
            return;
        }

        $emails = (array) config('customer_review.notify_emails', []);

        if ($emails !== []) {
            Notification::route('mail', $emails)->notify(new CustomerReviewDigest($records, $total));
            $this->info(sprintf('Súhrn odoslaný na %s.', implode(', ', $emails)));

            return;
        }

        $admins = User::query()
            ->role((array) config('customer_review.notify_roles', ['admin']))
            ->whereNotNull('email')
            ->get();

        if ($admins->isEmpty()) {
            $this->warn('Súhrn sa nemá komu poslať — nastavte CUSTOMER_REVIEW_NOTIFY.');

            return;
        }

        Notification::send($admins, new CustomerReviewDigest($records, $total));
        $this->info(sprintf('Súhrn odoslaný %d adminom.', $admins->count()));
    }

    private function customerLabel(CustomerReview $review): string
    {
        $customer = $review->customer;

        if ($customer === null) {
            return '(zmazaný)';
        }

        return trim((string) ($customer->company ?: $customer->name)) ?: '(bez názvu)';
    }
}
