<?php

namespace App\Services\Customers;

use App\Models\Customer;
use App\Models\Order;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Zákazník, ktorý je v tabuľke viackrát.
 *
 * Vzniká to tak, že checkout hľadá firmu cez `Customer::where('ico', ...)` až
 * po tom, čo `IcoFormater` číslo doplní na osem miest — kým človek IČO
 * nevyplní alebo ho napíše s medzerami, vznikne nový riadok. Tá istá obec tak
 * má dva záznamy, objednávky rozdelené medzi ne a v exporte figuruje dvakrát.
 *
 * Zlučuje sa vždy DO najstaršieho záznamu: na ňom visí história objednávok
 * a jeho ID je v už vystavených dokladoch. Ostatné sa nemažú natvrdo, len
 * soft-delete — keby sa zlúčenie ukázalo ako chyba, riadok je stále tam.
 */
class CustomerDuplicateService
{
    /**
     * Skupiny duplicít, od najväčšej.
     *
     * @return Collection<int, array{key: string, reason: string, customers: Collection<int, Customer>}>
     */
    public function groups(int $limit = 100): Collection
    {
        return $this->byIco($limit)
            ->concat($this->byNameAndCity($limit))
            ->take($limit)
            ->values();
    }

    /**
     * Rovnaké IČO. Najistejší znak — IČO je úradný identifikátor subjektu
     * a dva riadky s tým istým sú dva zápisy tej istej organizácie.
     */
    private function byIco(int $limit): Collection
    {
        $icos = DB::table('customers')
            ->selectRaw('LPAD(REGEXP_REPLACE(ico, "[^0-9]", ""), 8, "0") as normalized, COUNT(*) as total')
            ->whereNull('deleted_at')
            ->whereNotNull('ico')
            ->where('ico', '!=', '')
            ->groupBy('normalized')
            ->havingRaw('COUNT(*) > 1')
            ->orderByDesc('total')
            ->limit($limit)
            ->pluck('normalized');

        return $icos->map(function (string $ico) {
            $customers = Customer::query()
                ->withCount('orders')
                ->whereRaw('LPAD(REGEXP_REPLACE(ico, "[^0-9]", ""), 8, "0") = ?', [$ico])
                ->orderBy('id')
                ->get();

            return [
                'key' => 'ico:'.$ico,
                'reason' => __('customer_review.duplicates.reason_ico', ['ico' => $ico]),
                'customers' => $customers,
            ];
        })->filter(fn (array $group) => $group['customers']->count() > 1)->values();
    }

    /**
     * Rovnaký názov a mesto bez IČO.
     *
     * Slabší znak než IČO, preto sa berie len tam, kde IČO chýba — inak by
     * dve pobočky tej istej siete v jednom meste vyšli ako duplicita.
     * Porovnáva sa bez diakritiky a interpunkcie, lebo „Obec Pruské"
     * a „obec Pruske" sú ten istý zákazník napísaný dvakrát.
     */
    private function byNameAndCity(int $limit): Collection
    {
        $rows = Customer::query()
            ->whereNull('deleted_at')
            ->where(function ($query) {
                $query->whereNull('ico')->orWhere('ico', '');
            })
            ->get(['id', 'company', 'city']);

        return $rows
            ->groupBy(fn (Customer $c) => $this->normalize((string) $c->company).'|'.$this->normalize((string) $c->city))
            ->filter(fn (Collection $group, string $key) => $group->count() > 1 && trim($key, '|') !== '')
            ->take($limit)
            ->map(function (Collection $group, string $key) {
                $customers = Customer::query()
                    ->withCount('orders')
                    ->whereIn('id', $group->pluck('id'))
                    ->orderBy('id')
                    ->get();

                return [
                    'key' => 'name:'.$key,
                    'reason' => __('customer_review.duplicates.reason_name'),
                    'customers' => $customers,
                ];
            })
            ->values();
    }

    /**
     * Zlúči zákazníkov do jedného.
     *
     * Poradie krokov je dôležité: najprv sa doplnia chýbajúce údaje (kým sú
     * zdroje ešte živé), potom sa presunú väzby a až nakoniec sa zdroje
     * archivujú. Celé v transakcii — polovične zlúčený zákazník s objednávkami
     * na dvoch miestach by bol horší stav než dve kópie.
     *
     * @param  array<int, int>  $mergeIds
     * @return array{orders: int, users: int, filled: array<string, string>, merged: array<int, int>}
     */
    public function merge(Customer $keep, array $mergeIds): array
    {
        $sources = Customer::query()
            ->whereIn('id', $mergeIds)
            ->where('id', '!=', $keep->getKey())
            ->orderBy('id')
            ->get();

        if ($sources->isEmpty()) {
            return ['orders' => 0, 'users' => 0, 'filled' => [], 'merged' => []];
        }

        return DB::transaction(function () use ($keep, $sources) {
            $filled = $this->fillGaps($keep, $sources);

            $ids = $sources->pluck('id')->all();

            $orders = Order::query()->whereIn('customer_id', $ids)->update(['customer_id' => $keep->getKey()]);
            $users = User::query()->whereIn('customer_id', $ids)->update(['customer_id' => $keep->getKey()]);

            // Poznámka na archivovanom zázname je jediná stopa, podľa ktorej
            // sa dá zlúčenie spätne prečítať priamo v tabuľke.
            foreach ($sources as $source) {
                $source->forceFill([
                    'note' => trim(
                        __('customer_review.duplicates.merged_note', ['id' => $keep->getKey()])
                        .' '.(string) $source->note
                    ),
                    'status' => 'archived',
                ])->saveQuietly();

                $source->delete();
            }

            // Zlúčený záznam má iné údaje než pred chvíľou (doplnené medzery,
            // pribudnuté objednávky), takže starý posudok už neplatí. Nechá sa
            // prepočítať v najbližšom behu.
            $keep->review()->update([
                'due_at' => now(),
                'resolved_at' => null,
                'resolved_by' => null,
            ]);

            return [
                'orders' => $orders,
                'users' => $users,
                'filled' => $filled,
                'merged' => $ids,
            ];
        });
    }

    /**
     * Doplní do ponechaného záznamu to, čo v ňom chýba a niektorá z kópií to má.
     *
     * Nikdy nič neprepisuje — zlúčenie nemá byť príležitosť, ako ticho zmeniť
     * fakturačný údaj na tom zázname, ktorý zostáva.
     *
     * @return array<string, string>
     */
    private function fillGaps(Customer $keep, Collection $sources): array
    {
        $rules = app(CustomerDataRules::class);
        $attributes = $keep->getAttributes();
        $filled = [];

        foreach (CustomerDataRules::FIELDS as $field) {
            if (! $rules->isBlank($attributes[$field] ?? null)) {
                continue;
            }

            foreach ($sources as $source) {
                $value = $source->getAttributes()[$field] ?? null;

                if ($rules->isBlank($value)) {
                    continue;
                }

                $attributes[$field] = $value;
                $filled[$field] = (string) $value;
                break;
            }
        }

        if ($filled === []) {
            return [];
        }

        $keep->setRawAttributes($attributes);
        $keep->saveQuietly();

        return $filled;
    }

    /** Názov bez diakritiky, interpunkcie a veľkých písmen. */
    private function normalize(string $value): string
    {
        $value = mb_strtolower(trim($value));
        $value = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value) ?: $value;

        return trim(preg_replace('/[^a-z0-9]+/', ' ', $value) ?? '');
    }
}
