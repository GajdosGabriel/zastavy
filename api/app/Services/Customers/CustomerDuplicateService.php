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
        return $this->hydrate($this->groupRefs()->take($limit));
    }

    /**
     * Jedna stránka skupín. Zoznam odkazov na skupiny je lacný (len IČO
     * a ID), zákazníci sa dočítavajú iba pre skupiny na požadovanej stránke.
     *
     * @return array{groups: Collection<int, array{key: string, reason: string, customers: Collection<int, Customer>}>, total: int}
     */
    public function paginate(int $page, int $perPage): array
    {
        $refs = $this->groupRefs();

        return [
            'groups' => $this->hydrate($refs->slice(($page - 1) * $perPage, $perPage)),
            'total' => $refs->count(),
        ];
    }

    /**
     * Odkazy na všetky skupiny: najprv rovnaké IČO, potom rovnaký názov a mesto.
     *
     * @return Collection<int, array{type: string, key: string, ico?: string, ids?: array<int, int>}>
     */
    private function groupRefs(): Collection
    {
        return $this->icoRefs()->concat($this->nameRefs())->values();
    }

    /** @param  Collection<int, array{type: string, key: string, ico?: string, ids?: array<int, int>}>  $refs */
    private function hydrate(Collection $refs): Collection
    {
        return $refs->map(fn (array $ref) => $ref['type'] === 'ico'
            ? $this->icoGroup($ref['ico'])
            : $this->nameGroup($ref['key'], $ref['ids'])
        )->filter(fn (array $group) => $group['customers']->count() > 1)->values();
    }

    /**
     * Rovnaké IČO. Najistejší znak — IČO je úradný identifikátor subjektu
     * a dva riadky s tým istým sú dva zápisy tej istej organizácie.
     */
    private function icoRefs(): Collection
    {
        return DB::table('customers')
            ->selectRaw('LPAD(REGEXP_REPLACE(ico, "[^0-9]", ""), 8, "0") as normalized, COUNT(*) as total')
            ->whereNull('deleted_at')
            ->whereNotNull('ico')
            ->where('ico', '!=', '')
            ->groupBy('normalized')
            ->havingRaw('COUNT(*) > 1')
            ->orderByDesc('total')
            ->orderBy('normalized')
            ->pluck('normalized')
            ->map(fn (string $ico) => ['type' => 'ico', 'key' => 'ico:'.$ico, 'ico' => $ico]);
    }

    private function icoGroup(string $ico): array
    {
        return [
            'key' => 'ico:'.$ico,
            'reason' => __('customer_review.duplicates.reason_ico', ['ico' => $ico]),
            'customers' => Customer::query()
                ->withCount('orders')
                ->whereRaw('LPAD(REGEXP_REPLACE(ico, "[^0-9]", ""), 8, "0") = ?', [$ico])
                ->orderBy('id')
                ->get(),
        ];
    }

    /**
     * Rovnaký názov a mesto bez IČO.
     *
     * Slabší znak než IČO, preto sa berie len tam, kde IČO chýba — inak by
     * dve pobočky tej istej siete v jednom meste vyšli ako duplicita.
     * Porovnáva sa bez diakritiky a interpunkcie, lebo „Obec Pruské"
     * a „obec Pruske" sú ten istý zákazník napísaný dvakrát.
     */
    private function nameRefs(): Collection
    {
        return Customer::query()
            ->whereNull('deleted_at')
            ->where(function ($query) {
                $query->whereNull('ico')->orWhere('ico', '');
            })
            ->get(['id', 'company', 'city'])
            ->groupBy(fn (Customer $c) => $this->normalize((string) $c->company).'|'.$this->normalize((string) $c->city))
            ->filter(fn (Collection $group, string $key) => $group->count() > 1 && trim($key, '|') !== '')
            ->map(fn (Collection $group, string $key) => [
                'type' => 'name',
                'key' => 'name:'.$key,
                'ids' => $group->pluck('id')->all(),
            ])
            ->values();
    }

    private function nameGroup(string $key, array $ids): array
    {
        return [
            'key' => $key,
            'reason' => __('customer_review.duplicates.reason_name'),
            'customers' => Customer::query()
                ->with('primaryUser')
                ->withCount('orders')
                ->whereIn('id', $ids)
                ->orderBy('id')
                ->get(),
        ];
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

            \App\Services\SystemLog\Activity::record('customer', 'merged', 'Zlúčení zákazníci do #'.$keep->id,
                ['customer_id' => $keep->id, 'merged_ids' => $ids, 'orders_moved' => $orders, 'users_moved' => $users, 'filled' => $filled]);
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
            // `name` nie je stĺpec na `customers` — kontaktné osoby sa pri
            // zlúčení nedopĺňajú, ale presúvajú, a robí to krok s `users`.
            if ($field === 'name') {
                continue;
            }

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
