<?php

use App\Models\Customer;
use App\Services\Customers\CustomerDuplicateService;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;

// Zlúči duplicitných zákazníkov, pri ktorých niet pochybností: rovnaké IČO a zároveň
// rovnaké mesto aj PSČ (porovnáva sa bez diakritiky a interpunkcie; prázdna hodnota
// nevadí). Zlúčenie ide do najstaršieho záznamu cez CustomerDuplicateService — presunú
// sa objednávky a používatelia, chýbajúce údaje sa doplnia a kópie sa len soft-deletnú.
// Neisté prípady (iné mesto/PSČ, IČO 00000000, skupiny podľa názvu bez IČO) sa nechávajú
// človeku v zozname duplicít. Idempotentné — druhé spustenie už nenájde čo zlučovať.
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('customers') || ! Schema::hasTable('system_logs')) {
            return;
        }

        $service = app(CustomerDuplicateService::class);

        foreach ($service->groups(PHP_INT_MAX) as $group) {
            if (! $this->isCertain($group)) {
                continue;
            }

            /** @var Collection<int, Customer> $customers */
            $customers = $group['customers'];
            $service->merge($customers->first(), $customers->skip(1)->pluck('id')->all());
        }
    }

    public function down(): void
    {
        // Zlúčenie sa nevracia (kópie sú soft-deletnuté, dajú sa obnoviť ručne).
    }

    private function isCertain(array $group): bool
    {
        if (! str_starts_with($group['key'], 'ico:') || $group['key'] === 'ico:00000000') {
            return false;
        }

        $customers = $group['customers'];

        return $customers->map(fn (Customer $c) => $this->normalize($c->city))->filter()->unique()->count() <= 1
            && $customers->map(fn (Customer $c) => preg_replace('/\D/', '', (string) $c->getRawOriginal('postcode')))->filter()->unique()->count() <= 1;
    }

    private function normalize(?string $value): string
    {
        $value = mb_strtolower(trim((string) $value));
        $value = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value) ?: $value;

        return trim(preg_replace('/[^a-z0-9]+/', ' ', $value) ?? '');
    }
};
