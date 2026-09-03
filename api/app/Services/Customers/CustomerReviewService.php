<?php

namespace App\Services\Customers;

use App\Models\Customer;
use App\Models\CustomerReview;
use App\Services\Companies\CompanyRegistry;
use App\Services\OpenAI\ChatGPT;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Log;

/**
 * Post-kontrola údajov zákazníka — plánovanie, beh, oprava.
 *
 * Rozdelenie práce je zámerné a je to to isté rozdelenie ako pri kontrole
 * obsahu v projekte event:
 *
 *   schedule()  — beží pri každom uložení zákazníka, je lacný (jeden zápis)
 *                 a nič nevolá von. Iba povie „tento riadok bude treba pozrieť".
 *   run()       — beží z príkazu v malých dávkach, volá register a OpenAI.
 *
 * Uloženie formulára nesmie čakať na sieť. Checkout je posledný krok
 * objednávky a nikto nebude pozerať na spinner preto, lebo OpenAI práve
 * rozmýšľa nad veľkým písmenom v názve obce.
 *
 * Čo sa smie opraviť samo, hovorí `customer_review.autofix` a je to zámerne
 * krátky zoznam: len zmeny, po ktorých má pole tú istú hodnotu, len čitateľnú.
 * Všetko ostatné je návrh, ktorý potvrdí človek.
 */
class CustomerReviewService
{
    /**
     * Zámok proti rekurzii: opravy sa ukladajú cez saveQuietly(), ale keby
     * niekto observer neskôr prepol na iný hák, toto ho zastaví.
     */
    private static bool $applying = false;

    public function __construct(
        private readonly CustomerDataRules $rules,
        private readonly CompanyRegistry $registry,
        private readonly ChatGPT $chatGPT,
    ) {
    }

    // ------------------------------------------------------------ plánovanie

    /**
     * Naplánuje kontrolu, ak na ňu riadok dozrel.
     *
     * Volá sa zo `saved()`, teda pri každom uložení — musí byť lacná a tichá.
     */
    public function schedule(Customer $customer): void
    {
        if (self::$applying || ! config('customer_review.enabled', true)) {
            return;
        }

        // Lacná brzda pred akýmkoľvek dotazom. Uloženie kvôli statusu,
        // poznámke či prihláseniu sa kontroly netýka a import, ktorý prepíše
        // tisíc riadkov, by inak zaplatil dotaz navyše za každý z nich.
        if (! $customer->wasRecentlyCreated && ! $customer->wasChanged(CustomerDataRules::FIELDS)) {
            return;
        }

        $fingerprint = $this->rules->fingerprint($customer);
        $review = $customer->wasRecentlyCreated ? null : $this->reviewFor($customer);
        $dueAt = now()->addMinutes((int) config('customer_review.delay_minutes', 15));

        if ($review === null) {
            CustomerReview::create([
                'customer_id' => $customer->getKey(),
                'fingerprint' => $fingerprint,
                'due_at' => $dueAt,
            ]);

            return;
        }

        // Tie isté údaje sme už posúdili — znovu ich posielať by stálo peniaze
        // a vrátilo by to isté.
        if ($review->fingerprint === $fingerprint && $review->reviewed_at !== null) {
            return;
        }

        // Zmenené údaje rušia starý posudok: výhrady k predošlej verzii by po
        // oprave mátali a skóre by klamalo. Odbavenie tiež padá — je to nový
        // stav riadku, nie ten, ktorý niekto odklikol.
        $review->forceFill([
            'fingerprint' => $fingerprint,
            'due_at' => $dueAt,
            'score' => null,
            'summary' => null,
            'issues' => null,
            'applied' => null,
            'reviewed_at' => null,
            'resolved_at' => null,
            'resolved_by' => null,
            'last_error' => null,
        ])->save();
    }

    /**
     * Splatné kontroly, od najdlhšie čakajúcej.
     *
     * @return Collection<int, CustomerReview>
     */
    public function due(?int $limit = null): Collection
    {
        $limit ??= (int) config('customer_review.batch', 10);

        return CustomerReview::query()->due()->with('customer')->limit($limit)->get();
    }

    /** Naplánuje kontrolu všetkých zákazníkov — prvý prechod existujúcou tabuľkou. */
    public function scheduleAll(): int
    {
        $count = 0;

        Customer::query()->chunkById(200, function (Collection $customers) use (&$count) {
            foreach ($customers as $customer) {
                $review = $this->reviewFor($customer);
                $fingerprint = $this->rules->fingerprint($customer);

                if ($review === null) {
                    CustomerReview::create([
                        'customer_id' => $customer->getKey(),
                        'fingerprint' => $fingerprint,
                        // Bez odkladu: pri hromadnom prechode nie je čo dolaďovať,
                        // riadky roky nikto neotvoril.
                        'due_at' => now(),
                    ]);
                    $count++;

                    continue;
                }

                if ($review->reviewed_at !== null && $review->fingerprint === $fingerprint) {
                    continue;
                }

                $review->forceFill(['fingerprint' => $fingerprint, 'due_at' => now()])->save();
                $count++;
            }
        });

        return $count;
    }

    // ------------------------------------------------------------------ beh

    /**
     * Jedna kontrola. Vracia false, keď sa neposúdilo (zmiznutý zákazník, chyba).
     */
    public function run(CustomerReview $review): bool
    {
        $customer = $review->customer;

        if ($customer === null) {
            $review->forceFill(['due_at' => null])->save();

            return false;
        }

        $registry = $this->registryData($customer);

        $issues = $this->rules->check($customer);
        $issues = array_merge($issues, $this->registryIssues($customer, $registry));

        $aiScore = null;
        $aiSummary = null;
        $aiError = null;

        // Chýbajúci kľúč nie je chyba behu — je to „AI vrstva nie je zapnutá".
        // Bez tejto podmienky by sa každý riadok o šesť hodín márne vracal
        // do fronty a v logu by pribúdalo to isté hlásenie.
        if (config('customer_review.ai', true) && (string) config('services.openai.key', '') !== '') {
            try {
                $ai = $this->chatGPT->extractCustomerReview($this->aiPayload($customer), $registry);
                $aiScore = $ai['score'];
                $aiSummary = $ai['summary'];
                $issues = array_merge($issues, $this->normalizeAiIssues($customer, $ai['issues']));
            } catch (\Throwable $e) {
                Log::warning('AI kontrola zákazníka zlyhala.', [
                    'customer_id' => $customer->getKey(),
                    'error' => $e->getMessage(),
                ]);

                $aiError = mb_substr($e->getMessage(), 0, 255);
            }
        }

        $applied = $this->applyAutofixes($customer, $issues, $registry);

        // Po oprave sa pravidlá prepočítajú: nález, ktorý automat práve
        // vyriešil, nemá čo svietiť v zozname pre človeka.
        if ($applied !== []) {
            $customer->refresh();
            $issues = array_merge(
                $this->rules->check($customer),
                $this->registryIssues($customer, $registry),
                array_values(array_filter($issues, static fn (array $i) => ($i['source'] ?? '') === 'ai')),
            );
        }

        $issues = $this->rules->sort($this->dedupe($issues));

        $review->forceFill([
            'fingerprint' => $this->rules->fingerprint($customer->refresh()),
            'score' => $this->score($issues, $aiScore),
            // Len veta od modelu. Zhrnutie podľa počtu nálezov si dopočíta
            // čítajúca strana vo svojom jazyku — uložiť ho tu by znamenalo
            // zamraziť slovenčinu v databáze.
            'summary' => $aiSummary === null ? null : mb_substr($aiSummary, 0, 500),
            'issues' => $issues,
            'applied' => $applied === [] ? null : $applied,
            'reviewed_at' => now(),
            // Nálezy pravidiel a registra sú hotové a ukladajú sa aj po
            // výpadku OpenAI — riadok sa len vráti do fronty, aby sa AI vrstva
            // dobehla. Nie hneď: výpadok by inak celú dávku držal a v každom
            // behu by sa minula na tie isté zlyhávajúce riadky.
            'due_at' => $aiError === null ? null : now()->addHours(6),
            'last_error' => $aiError,
        ])->save();

        return true;
    }

    // -------------------------------------------------------------- opravy

    /**
     * Prijme návrhy, ktoré admin odklikol v detaile zákazníka.
     *
     * @param  array<int, int>  $indexes  poradové čísla výhrad v `issues`
     * @return array<int, array<string, mixed>>  čo sa naozaj zmenilo
     */
    public function applySuggestions(CustomerReview $review, array $indexes, ?int $userId = null): array
    {
        $customer = $review->customer;

        if ($customer === null) {
            return [];
        }

        $issues = (array) ($review->issues ?? []);
        $changes = [];

        foreach ($indexes as $index) {
            $issue = $issues[$index] ?? null;
            $suggested = $issue['suggested'] ?? null;
            $field = $issue['field'] ?? null;

            if ($issue === null || $field === null || ! in_array($field, CustomerDataRules::FIELDS, true)) {
                continue;
            }

            // Nález bez návrhu sa prijať nedá — nie je čo zapísať. Tie sú
            // v paneli len ako informácia a admin ich prepíše ručne.
            if ($suggested === null || $suggested === '') {
                continue;
            }

            $before = $this->rules->raw($customer, $field);

            if ($before === $suggested) {
                continue;
            }

            $changes[] = [
                'field' => $field,
                'from' => $before,
                'to' => $suggested,
                'fix' => $issue['fix'] ?? 'manual',
                'source' => $issue['source'] ?? 'rule',
                'by' => $userId,
                'at' => now()->toIso8601String(),
            ];

            $this->write($customer, $field, $suggested);
        }

        if ($changes === []) {
            return [];
        }

        $this->persist($customer);

        // Prijatý návrh musí zo zoznamu zmiznúť, inak by ho admin videl aj po
        // kliknutí. Zvyšné nálezy sa prepočítajú z už opraveného riadku.
        $review->forceFill([
            'applied' => array_merge((array) ($review->applied ?? []), $changes),
            'issues' => $this->rules->sort($this->dedupe(array_merge(
                $this->rules->check($customer),
                // Nálezy registra a AI sa neprepočítavajú (to by bolo ďalšie
                // volanie von); ostávajú okrem tých na poliach, ktoré sa práve
                // opravili — tie sú vybavené.
                array_values(array_filter(
                    $issues,
                    static fn (array $i) => in_array($i['source'] ?? '', ['ai', 'registry'], true)
                        && ! in_array($i['field'] ?? '', array_column($changes, 'field'), true),
                )),
            ))),
            'fingerprint' => $this->rules->fingerprint($customer),
        ])->save();

        return $changes;
    }

    /**
     * Vráti automatickú opravu späť.
     *
     * Automat mení fakturačné údaje bez toho, aby sa spýtal — pri normalizácii
     * telefónov je to naraz stovky riadkov. Bez tlačidla „vrátiť" by jediná
     * cesta späť viedla cez ručné prepísanie podľa e-mailu, a to je pri takom
     * počte len teoretická možnosť.
     *
     * Vracia sa presne to, čo je zapísané v `applied` — teda hodnota, ktorá
     * v stĺpci naozaj bola, nie odhad.
     *
     * @param  array<int, int>  $indexes  poradové čísla zmien v `applied`
     * @return array<int, array<string, mixed>>  čo sa vrátilo
     */
    public function revertApplied(CustomerReview $review, array $indexes, ?int $userId = null): array
    {
        $customer = $review->customer;

        if ($customer === null) {
            return [];
        }

        $applied = (array) ($review->applied ?? []);
        $reverted = [];
        $keep = [];

        foreach ($applied as $index => $change) {
            $field = $change['field'] ?? null;

            if (! in_array($index, $indexes, true) || ! in_array($field, CustomerDataRules::FIELDS, true)) {
                $keep[] = $change;

                continue;
            }

            // Vracia sa len vtedy, keď je v poli stále to, čo tam automat
            // zapísal. Ak medzitým hodnotu zmenil človek, jeho zmena má
            // prednosť a vrátenie by ju ticho prepísalo.
            if ($this->rules->raw($customer, $field) !== ($change['to'] ?? null)) {
                $keep[] = $change;

                continue;
            }

            $this->write($customer, $field, $change['from'] ?? null);

            $reverted[] = $change + ['reverted_by' => $userId, 'reverted_at' => now()->toIso8601String()];
        }

        if ($reverted === []) {
            return [];
        }

        $this->persist($customer);

        // Vrátený stav treba posúdiť odznova — pôvodná hodnota je zrejme tá,
        // ktorú kontrola pred chvíľou označila za chybnú, a má sa objaviť
        // medzi nálezmi, nie zmiznúť.
        $review->forceFill([
            'applied' => $keep === [] ? null : array_values($keep),
            'issues' => $this->rules->sort($this->dedupe(array_merge(
                $this->rules->check($customer),
                array_values(array_filter(
                    (array) ($review->issues ?? []),
                    static fn (array $i) => in_array($i['source'] ?? '', ['ai', 'registry'], true),
                )),
            ))),
            'fingerprint' => $this->rules->fingerprint($customer),
        ])->save();

        return $reverted;
    }

    /** Odbaví posudok — nálezy ostanú zapísané, ale prestanú svietiť. */
    public function resolve(CustomerReview $review, ?int $userId = null): void
    {
        $review->forceFill([
            'resolved_at' => now(),
            'resolved_by' => $userId,
            'due_at' => null,
        ])->save();
    }

    public function reviewFor(Customer $customer): ?CustomerReview
    {
        return CustomerReview::query()->where('customer_id', $customer->getKey())->first();
    }

    // -------------------------------------------------------------- vnútro

    /**
     * Opravy, ktoré sa smú spraviť samé.
     *
     * @return array<int, array<string, mixed>>
     */
    private function applyAutofixes(Customer $customer, array &$issues, ?array $registry): array
    {
        $allowed = (array) config('customer_review.autofix', []);
        $changes = [];

        foreach ($issues as $issue) {
            $fix = $issue['fix'] ?? null;
            $field = $issue['field'] ?? null;

            if ($fix === null || $field === null || ! in_array($fix, $allowed, true)) {
                continue;
            }

            // Doplnenie daňového čísla z registra nie je oprava textu, má
            // vlastný zdroj údaja — rieši sa nižšie, nie tu.
            if ($fix === 'registry_tax_ids') {
                continue;
            }

            $before = $this->rules->raw($customer, $field);
            $after = $issue['suggested'] ?? null;

            // `blank_to_null` je jediná oprava bez navrhovanej hodnoty:
            // výsledkom je prázdna hodnota, nie iný text.
            if ($fix === 'blank_to_null') {
                $after = null;
            }

            if ($before === $after) {
                continue;
            }

            $this->write($customer, $field, $after);

            $changes[] = [
                'field' => $field,
                'from' => $before,
                'to' => $after,
                'fix' => $fix,
                'source' => $issue['source'] ?? 'rule',
                'at' => now()->toIso8601String(),
            ];
        }

        $changes = array_merge($changes, $this->fillTaxIdsFromRegistry($customer, $registry, $allowed));

        if ($changes !== []) {
            $this->persist($customer);
        }

        return $changes;
    }

    /**
     * Doplní CHÝBAJÚCE DIČ a IČ DPH z registra.
     *
     * Nikdy neprepisuje vyplnené — keď sa naše číslo od registra líši, je to
     * nález pre človeka (registryIssues()), nie vec na tiché prepísanie.
     * Rozdiel môže znamenať, že IČO patrí inému subjektu, a to sa opravou
     * daňového čísla len zamaskuje.
     */
    private function fillTaxIdsFromRegistry(Customer $customer, ?array $registry, array $allowed): array
    {
        if ($registry === null || ! in_array('registry_tax_ids', $allowed, true)) {
            return [];
        }

        $changes = [];

        foreach (['dic', 'ic_dic'] as $field) {
            $current = $this->rules->raw($customer, $field);
            $fromRegistry = trim((string) ($registry[$field] ?? ''));

            if (! $this->rules->isBlank($current) || $fromRegistry === '') {
                continue;
            }

            $this->write($customer, $field, $fromRegistry);

            $changes[] = [
                'field' => $field,
                'from' => $current,
                'to' => $fromRegistry,
                'fix' => 'registry_tax_ids',
                'source' => 'registry',
                'at' => now()->toIso8601String(),
            ];
        }

        return $changes;
    }

    /**
     * Zápis do stĺpca s obídením castov.
     *
     * Casty na `customers` sú písané pre formulár: PhoneFormater z null spraví
     * prázdny reťazec, IcoFormater doplní nuly. Kontrola už pracuje s tvarom,
     * v akom má hodnota v stĺpci ležať, takže ďalší prevod by jej výsledok
     * zmenil — a `blank_to_null` by nikdy nedokázal zapísať NULL.
     */
    private function write(Customer $customer, string $field, ?string $value): void
    {
        $attributes = $customer->getAttributes();
        $attributes[$field] = $value;

        $customer->setRawAttributes($attributes);

        // Slug visí na mene a mutátor sme práve obišli; bez toho by po oprave
        // mena ostal slug starý.
        if ($field === 'name' && $value !== null) {
            $attributes['slug'] = \Illuminate\Support\Str::slug($value, '-');
            $customer->setRawAttributes($attributes);
        }
    }

    /** Uloží zákazníka tak, aby uloženie nenaplánovalo ďalšiu kontrolu. */
    private function persist(Customer $customer): void
    {
        self::$applying = true;

        try {
            $nameChanged = $customer->isDirty('name');

            $customer->saveQuietly();

            if ($nameChanged) {
                $this->syncContactName($customer);
            }
        } finally {
            self::$applying = false;
        }
    }

    /**
     * Prepíše meno aj kontaktnej osobe.
     *
     * Meno kontaktu je v tabuľke dvakrát — v `customers.name` a v
     * `users.username` — a formulár aj CustomerResource ukazujú to druhé.
     * Bez tohto kroku by oprava mena vyzerala, že nič nespravila: v stĺpci by
     * bola nová hodnota, na obrazovke stará.
     *
     * Prepisuje sa len vtedy, keď obe strany doteraz držali to isté. Keď sa
     * už rozišli, je to skutočný rozdiel dvoch údajov a nie je na kontrole,
     * aby rozhodla, ktorý z nich je ten správny.
     */
    private function syncContactName(Customer $customer): void
    {
        $contact = $customer->primaryUser ?? $customer->latestUser;

        if ($contact === null) {
            return;
        }

        $before = (string) $customer->getOriginal('name');
        $after = (string) $customer->getAttributes()['name'];

        if ($after === '' || trim((string) $contact->username) !== trim($before)) {
            return;
        }

        $parts = preg_split('/\s+/u', trim($after), 2) ?: [];

        $contact->forceFill([
            'name' => $after,
            'username' => $after,
            'slug' => \Illuminate\Support\Str::slug($after),
            'firstName' => $parts[0] ?? $after,
            'lastName' => $parts[1] ?? '',
        ])->saveQuietly();
    }

    private function registryData(Customer $customer): ?array
    {
        if (! config('customer_review.registry', true)) {
            return null;
        }

        $ico = trim((string) $this->rules->raw($customer, 'ico'));
        $digits = preg_replace('/\D+/', '', $ico) ?? '';

        if (strlen($digits) < 6) {
            return null;
        }

        // Do registra sa nechodí s číslom, o ktorom už vieme, že je zlé —
        // odpoveď by bola „nenašlo sa" a nález o preklepe už máme.
        $padded = str_pad($digits, 8, '0', STR_PAD_LEFT);

        if (! $this->rules->icoChecksumValid($padded)) {
            return null;
        }

        return $this->registry->find($padded);
    }

    /**
     * Rozdiely oproti obchodnému registru.
     *
     * Register je autorita na názov a sídlo, takže jeho nález má vyššiu váhu
     * než čokoľvek od AI — ale opraviť sa sám nesmie: subjekt môže mať
     * doručovaciu adresu inde než sídlo a názov na faktúre skrátený legitímne.
     */
    private function registryIssues(Customer $customer, ?array $registry): array
    {
        if ($registry === null) {
            return [];
        }

        $issues = [];

        foreach (['dic', 'ic_dic'] as $field) {
            $current = trim((string) $this->rules->raw($customer, $field));
            $expected = trim((string) ($registry[$field] ?? ''));

            if ($expected === '' || $this->rules->isBlank($current) || $current === $expected) {
                continue;
            }

            $issues[] = [
                'field' => $field,
                'severity' => 'error',
                'source' => 'registry',
                // Názov poľa vo vete nie je — panel aj e-mail ho ukazujú
                // vedľa hlásenia, tak by tam stál dvakrát.
                'key' => 'customer_review.issues.registry_tax_mismatch',
                'params' => [],
                'current' => $current,
                'suggested' => $expected,
                'fix' => null,
            ];
        }

        $company = trim((string) $this->rules->raw($customer, 'company'));
        $official = $this->officialName((string) ($registry['company'] ?? ''));

        if ($official !== '' && $company !== '' && ! $this->sameCompanyName($company, $official)) {
            $issues[] = [
                'field' => 'company',
                'severity' => 'notice',
                'source' => 'registry',
                'key' => 'customer_review.issues.registry_company_differs',
                'params' => [],
                'current' => $company,
                'suggested' => $official,
                'fix' => null,
            ];
        }

        return $issues;
    }

    /**
     * Názov firmy z registra bez adresy.
     *
     * Register vracia úradné znenie aj s adresou — „Gymnázium Andreja
     * Sládkoviča, J.A Komenského 18, Banská Bystrica". Navrhnúť ho ako názov
     * firmy by bolo v priamom rozpore s pravidlom, ktoré o riadok vyššie
     * hlási adresu v názve ako chybu.
     */
    private function officialName(string $name): string
    {
        $name = trim($name);
        $parts = explode(',', $name);

        if (count($parts) < 2) {
            return $name;
        }

        $head = trim($parts[0]);
        $tail = trim(implode(',', array_slice($parts, 1)));

        // Odrezáva sa len vtedy, keď to za čiarkou naozaj vyzerá na adresu
        // (číslo domu, PSČ). „Ministerstvo vnútra SR, sekcia..." nie je adresa
        // a skrátením by z názvu vypadla podstatná časť.
        return $head !== '' && preg_match('/\d/', $tail) === 1 ? $head : $name;
    }

    /**
     * Sú to dva zápisy toho istého názvu?
     *
     * Porovnáva sa bez diakritiky, interpunkcie a veľkosti písmen — inak by
     * „Obec Pruské" a „Obec Pruske" boli dva rôzne subjekty a nález o rozdiele
     * by prišiel pri každom druhom zákazníkovi.
     */
    private function sameCompanyName(string $a, string $b): bool
    {
        $normalize = static function (string $value): string {
            $value = mb_strtolower($value);
            $value = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value) ?: $value;

            return trim(preg_replace('/[^a-z0-9]+/', ' ', $value) ?? '');
        };

        return $normalize($a) === $normalize($b);
    }

    /**
     * Údaje, ktoré smie vidieť model.
     *
     * E-mail ani telefón v zozname nie sú a nesmú pribudnúť — sú to osobné
     * údaje a na posúdenie textových polí ich netreba (viď PromptCustomerReview).
     */
    private function aiPayload(Customer $customer): array
    {
        $payload = [];

        foreach (['name', 'company', 'street', 'city', 'ico'] as $field) {
            $payload[$field] = $this->rules->raw($customer, $field);
        }

        return $payload;
    }

    /**
     * Odpoveď modelu do jednotného tvaru nálezu.
     *
     * Návrh, ktorý sa od súčasnej hodnoty nelíši, sa zahadzuje — model občas
     * „opraví" pole na to isté, čo v ňom je, a v paneli by z toho bolo
     * tlačidlo, ktoré nič nespraví.
     */
    private function normalizeAiIssues(Customer $customer, array $issues): array
    {
        $out = [];

        foreach ($issues as $issue) {
            $field = $issue['field'] ?? null;

            if (! in_array($field, \App\Services\OpenAI\PromptCustomerReview::FIELDS, true)) {
                continue;
            }

            $current = $this->rules->raw($customer, $field);
            $suggested = $issue['suggested'] ?? null;
            $suggested = $suggested === null ? null : trim((string) $suggested);

            if ($suggested === '' || $suggested === $current) {
                $suggested = null;
            }

            $out[] = [
                'field' => $field,
                'severity' => $issue['severity'] ?? 'notice',
                'source' => 'ai',
                'message' => trim((string) ($issue['message'] ?? '')),
                'current' => $current,
                'suggested' => $suggested,
                // AI návrh sa nikdy neaplikuje sám. Je to odhad, nie údaj
                // zo zdroja — potvrdiť ho musí človek.
                'fix' => null,
            ];
        }

        return $out;
    }

    /** Jedno pole, jedna výhrada od jedného zdroja — zvyšok je šum. */
    private function dedupe(array $issues): array
    {
        $seen = [];
        $out = [];

        foreach ($issues as $issue) {
            // Nález od pravidiel a registra rozlišuje kľúč, nález od AI text —
            // model si vetu formuluje sám a kľúč nemá.
            $key = ($issue['field'] ?? '').'|'.($issue['source'] ?? '')
                .'|'.($issue['key'] ?? $issue['message'] ?? '');

            if (isset($seen[$key])) {
                continue;
            }

            $seen[$key] = true;
            $out[] = $issue;
        }

        return $out;
    }

    /**
     * Skóre riadku. Keď AI odpovedala, berie sa to nižšie z jej hodnotenia
     * a hodnotenia podľa pravidiel — pravidlo o neplatnom IČO nesmie prehlušiť
     * spokojné „vyzerá to dobre" od modelu.
     */
    private function score(array $issues, ?int $aiScore): int
    {
        $penalty = 0;

        foreach ($issues as $issue) {
            $penalty += match ($issue['severity'] ?? 'notice') {
                'error' => 30,
                'warning' => 15,
                default => 5,
            };
        }

        $rulesScore = max(0, 100 - $penalty);

        return $aiScore === null ? $rulesScore : min($rulesScore, $aiScore);
    }

}
