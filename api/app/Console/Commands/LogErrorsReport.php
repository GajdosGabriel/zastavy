<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Notifications\LogErrorsFound;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;

/**
 * Pošle e-mailom chyby, ktoré pribudli v laravel.log od posledného behu,
 * aby sa log nemusel kontrolovať ručne.
 *
 * Mail neodchádza v okamihu chyby, ale z plánovača: funguje aj pri výpadku
 * DB či cache (pozícia v logu je v súbore), nezacyklí sa pri chybe pošty
 * a tisíc rovnakých chýb je jeden riadok s počtom. Šum z výpadkov cudzích
 * služieb sa vynechá (config/logging.php → report.ignore).
 *
 * Beh je krátky (číta len prírastok), takže sa zmestí aj do webcronu.
 */
class LogErrorsReport extends Command
{
    protected $signature = 'logs:report-errors
        {--dry-run : Len vypísať, čo by sa poslalo, a pozíciu neposunúť}';

    protected $description = 'Pošle e-mailom nové opraviteľné chyby z laravel.log';

    private const LEVELS = ['ERROR', 'CRITICAL', 'ALERT', 'EMERGENCY'];

    /** Hlavička záznamu Monologu: [2026-10-23 14:05:01] production.ERROR: správa */
    private const HEADER = '/^\[(\d{4}-\d\d-\d\d[ T][^\]]*)\] ([\w.-]+)\.([A-Z]+): (.*)$/m';

    public function handle(): int
    {
        $config = config('logging.report');

        if (! $config['enabled']) {
            return self::SUCCESS;
        }

        $path = $config['path'];
        $size = is_file($path) ? (int) filesize($path) : 0;
        $state = $this->readState($config['state']);

        // Prvý beh nehlási celú históriu logu, len si zapamätá jeho koniec.
        if ($state === null) {
            $this->option('dry-run') || $this->writeState($config['state'], $size);
            $this->info("Začínam od pozície {$size}.");

            return self::SUCCESS;
        }

        // Log zmazaný alebo vyprázdnený — číta sa od začiatku.
        $offset = $state > $size ? 0 : $state;

        if ($offset === $size) {
            return self::SUCCESS;
        }

        [$chunk, $next] = $this->readChunk($path, $offset, $size, (int) $config['max_bytes']);
        $groups = $this->groups($chunk, $config['ignore'], $config['skip_environments']);

        if ($this->option('dry-run')) {
            foreach ($groups as $group) {
                $this->line("{$group['count']}× {$group['message']}".($group['location'] ? " ({$group['location']})" : ''));
            }
            $this->info(count($groups).' druhov chýb, nič sa neposlalo.');

            return self::SUCCESS;
        }

        if ($groups !== []) {
            $recipients = $this->recipients();

            if ($recipients === null) {
                $this->warn('Chyby sa nemá komu poslať — nastavte LOG_REPORT_EMAIL.');

                return self::FAILURE;
            }

            // Keď mail zlyhá, pozícia sa neposunie a chyby prídu nabudúce.
            $recipients(new LogErrorsFound($groups));
        }

        $this->writeState($config['state'], $next);
        $this->info(count($groups).' druhov chýb.');

        return self::SUCCESS;
    }

    /**
     * @return array{string, int} prečítaný text a pozícia, kde nabudúce pokračovať
     */
    private function readChunk(string $path, int $offset, int $size, int $maxBytes): array
    {
        $handle = fopen($path, 'rb');
        fseek($handle, $offset);
        $chunk = (string) fread($handle, min($maxBytes, $size - $offset));
        fclose($handle);

        // Ak sa nečítalo do konca, posledný (možno useknutý) záznam počká.
        if ($offset + strlen($chunk) < $size && ($cut = strrpos($chunk, "\n[")) !== false) {
            $chunk = substr($chunk, 0, $cut + 1);
        }

        return [$chunk, $offset + strlen($chunk)];
    }

    /**
     * Chyby zoskupené podľa správy bez čísel (ID, časy, porty), najčastejšie prvé.
     *
     * @param  list<string>  $ignore
     * @param  list<string>  $skipEnvironments
     * @return list<array{message: string, class: ?string, location: ?string, count: int, first: string, last: string}>
     */
    private function groups(string $chunk, array $ignore, array $skipEnvironments): array
    {
        $records = preg_split('/^(?=\[\d{4}-\d\d-\d\d[ T][^\]]*\] [\w.-]+\.[A-Z]+: )/m', $chunk, -1, PREG_SPLIT_NO_EMPTY);
        $groups = [];

        foreach ($records as $record) {
            if (! preg_match(self::HEADER, $record, $header)
                || ! in_array($header[3], self::LEVELS, true)
                || in_array($header[2], $skipEnvironments, true)) {
                continue;
            }

            $class = preg_match('/\[object\] \(([\w\\\\]+)\(code: /', $record, $m) ? stripslashes($m[1]) : null;
            $message = trim(Str::before($header[4], ' {"'));

            if ($this->ignored($message.' '.$class, $ignore)) {
                continue;
            }

            $key = md5($class.'|'.Str::limit(preg_replace('/\d+/', 'N', $message), 200));

            // Bez výnimky je podstata v kontexte (napr. operations.unhealthy → ktorá kontrola).
            $shown = $class === null ? trim($header[4]) : ($message !== '' ? $message : $class);

            $groups[$key] ??= [
                'message' => Str::limit($shown, 300),
                'class' => $class,
                'location' => $this->location($record),
                'count' => 0,
                'first' => $header[1],
            ];
            $groups[$key]['count']++;
            $groups[$key]['last'] = $header[1];
        }

        usort($groups, fn ($a, $b) => $b['count'] <=> $a['count']);

        return $groups;
    }

    /** @param  list<string>  $ignore */
    private function ignored(string $haystack, array $ignore): bool
    {
        foreach ($ignore as $needle) {
            if ($needle !== '' && stripos($haystack, $needle) !== false) {
                return true;
            }
        }

        return false;
    }

    /**
     * Prvé miesto vo vlastnom kóde (app/, routes/, resources/, config/,
     * database/), kde chyba nastala — vendor nič nepovie.
     */
    private function location(string $record): ?string
    {
        $record = str_replace(['\\\\', '\\'], '/', $record);
        $base = preg_quote(str_replace('\\', '/', base_path()).'/', '/');
        $own = '(?:app|routes|resources|config|database|bootstrap)\/[^\s:()]+';

        $patterns = [
            "/ at {$base}({$own}):(\d+)\)/",
            "/#\d+ {$base}({$own})\((\d+)\)/",
            "/ in {$base}({$own}):(\d+)/",
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $record, $m)) {
                return $m[1].':'.$m[2];
            }
        }

        return null;
    }

    /** @return (callable(LogErrorsFound): void)|null */
    private function recipients(): ?callable
    {
        $emails = (array) config('logging.report.emails', []);

        if ($emails !== []) {
            return fn ($notification) => Notification::route('mail', $emails)->notify($notification);
        }

        $users = User::query()
            ->role((array) config('logging.report.roles', []))
            ->whereNotNull('email')
            ->get();

        return $users->isEmpty() ? null : fn ($notification) => Notification::send($users, $notification);
    }

    private function readState(string $file): ?int
    {
        $data = is_file($file) ? json_decode((string) file_get_contents($file), true) : null;

        return isset($data['offset']) ? (int) $data['offset'] : null;
    }

    private function writeState(string $file, int $offset): void
    {
        file_put_contents($file, json_encode(['offset' => $offset, 'at' => now()->toIso8601String()]));
    }
}
