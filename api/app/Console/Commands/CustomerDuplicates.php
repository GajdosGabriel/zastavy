<?php

namespace App\Console\Commands;

use App\Models\Customer;
use App\Services\Customers\CustomerDuplicateService;
use Illuminate\Console\Command;

/**
 * Zákazníci, ktorí sú v tabuľke viackrát.
 *
 * Bez `--merge` len vypíše, čo našiel — a to je predvolené správanie zámerne.
 * Zlúčenie presúva objednávky a archivuje záznamy; taký zásah sa nemá stať
 * preto, že niekto spustil príkaz zvedavosti.
 *
 *   php artisan app:customer-duplicates
 *   php artisan app:customer-duplicates --merge --limit=10
 */
class CustomerDuplicates extends Command
{
    protected $signature = 'app:customer-duplicates
        {--limit=100 : Koľko skupín spracovať}
        {--merge : Naozaj zlúčiť do najstaršieho záznamu (inak len výpis)}';

    protected $description = 'Nájde zákazníkov s rovnakým IČO (alebo názvom a mestom) a vie ich zlúčiť';

    public function handle(CustomerDuplicateService $service): int
    {
        $groups = $service->groups(max(1, (int) $this->option('limit')));

        if ($groups->isEmpty()) {
            $this->info('Žiadne duplicity.');

            return self::SUCCESS;
        }

        $merge = (bool) $this->option('merge');
        $redundant = 0;
        $movedOrders = 0;

        foreach ($groups as $group) {
            /** @var \Illuminate\Support\Collection<int, Customer> $customers */
            $customers = $group['customers'];

            // Ponecháva sa najstarší záznam — na ňom visí história objednávok
            // a jeho ID je vo vystavených dokladoch.
            $keep = $customers->first();
            $rest = $customers->skip(1);
            $redundant += $rest->count();

            $this->line($group['reason']);
            $this->line(sprintf(
                '  ostáva  #%d %s (%d obj.)',
                $keep->id,
                $keep->company ?: $keep->name,
                $keep->orders_count ?? 0,
            ));

            foreach ($rest as $customer) {
                $this->line(sprintf(
                    '  %s #%d %s (%d obj.)',
                    $merge ? 'zlučuje' : 'zlúčilo by',
                    $customer->id,
                    $customer->company ?: $customer->name,
                    $customer->orders_count ?? 0,
                ));
            }

            if (! $merge) {
                continue;
            }

            $result = $service->merge($keep, $rest->pluck('id')->all());
            $movedOrders += $result['orders'];

            if ($result['filled'] !== []) {
                $this->line('  doplnené: '.implode(', ', array_keys($result['filled'])));
            }
        }

        $this->info(sprintf(
            '%s: %d skupín, %d nadbytočných záznamov%s.',
            $merge ? 'Zlúčené' : 'Nájdené',
            $groups->count(),
            $redundant,
            $merge ? sprintf(', presunutých objednávok %d', $movedOrders) : '',
        ));

        if (! $merge) {
            $this->comment('Zlúčenie spustíte prepínačom --merge.');
        }

        return self::SUCCESS;
    }
}
