<?php

namespace App\Services\Customers;

use App\Models\Customer;

/**
 * Prvá vrstva post-kontroly: čo o riadku vieme povedať bez siete a bez AI.
 *
 * Sem patrí len to, na čo sa dá napísať pravidlo s istou odpoveďou — formát,
 * kontrolná číslica, prázdna hodnota zapísaná ako znak. Nič, čo by muselo
 * hádať („je toto meno firmy alebo človeka?"); to je práca pre AI a stojí to
 * peniaze, tak nech dostane už len zvyšok.
 *
 * Nálezy majú jednotný a zámerne plochý tvar:
 *
 *   field      — stĺpec v `customers`
 *   severity   — error | warning | notice (poradie je aj poradím pri triedení)
 *   source     — rule | registry | ai (odkiaľ nález je, kvôli dôvere)
 *   message    — jedna veta pre človeka
 *   current    — čo je v poli teraz
 *   suggested  — čo tam má byť, alebo null keď to vieme len oznámiť
 *   fix        — kľúč z `customer_review.autofix`, keď sa oprava smie spraviť
 *                sama; null pri všetkom, čo mení význam údaja
 */
class CustomerDataRules
{
    /** Závažnosti od najmenšej — poradie je aj poradím pri triedení. */
    public const SEVERITIES = ['notice', 'warning', 'error'];

    /** Polia, ktoré kontrola posudzuje. Odtlačok sa počíta z nich. */
    public const FIELDS = [
        'name', 'company', 'email', 'phone',
        'street', 'postcode', 'city',
        'ico', 'dic', 'ic_dic',
    ];

    /**
     * Hodnoty, ktoré človek napíše namiesto toho, aby pole nechal prázdne.
     * V databáze potom nie sú prázdne, takže ich žiadne `whereNull` nenájde
     * a na faktúre sa vytlačia ako „DIČ: -".
     */
    private const BLANKS = ['-', '--', '.', ',', 'x', 'n/a', 'neviem', 'nemam', 'nemám', '0'];

    /**
     * Stĺpce, do ktorých sa dá zapísať NULL.
     *
     * `name`, `postcode` a `city` sú v migrácii NOT NULL, takže oprava
     * „prázdny reťazec → prázdna hodnota" by na nich skončila SQL chybou.
     * Nález sa aj tak vypíše, len bez tlačidla — také pole treba vyplniť,
     * nie vyprázdniť.
     */
    private const NULLABLE = ['company', 'email', 'phone', 'street', 'ico', 'dic', 'ic_dic'];

    /**
     * Tie isté pravidlá nad ešte neuloženými údajmi z formulára.
     *
     * Kvôli tomuto je vrstva pravidiel oddelená od zvyšku kontroly: to isté,
     * čo po uložení nájde nočný beh, vie formulár povedať okamžite a zadarmo,
     * kým človek ešte píše. Bez toho by sme chyby len zbierali namiesto toho,
     * aby sme ich nepustili dnu.
     *
     * Pracuje sa cez neuložený model, nie cez pole — pravidlá čítajú hodnoty
     * `getAttributes()`, teda presne to, čo by šlo do stĺpca, a nemusia
     * existovať dvakrát.
     *
     * @param  array<string, mixed>  $attributes
     * @return array<int, array<string, mixed>>
     */
    public function checkAttributes(array $attributes): array
    {
        $customer = new Customer();

        $raw = [];

        foreach (self::FIELDS as $field) {
            if (! array_key_exists($field, $attributes)) {
                continue;
            }

            $value = $attributes[$field];
            $raw[$field] = $value === null ? null : (string) $value;
        }

        $customer->setRawAttributes($raw);

        return $this->sort($this->check($customer));
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function check(Customer $customer): array
    {
        $issues = [];

        foreach (self::FIELDS as $field) {
            $issues = array_merge(
                $issues,
                $this->checkBlank($customer, $field),
                $this->checkWhitespace($customer, $field),
            );
        }

        return array_merge(
            $issues,
            $this->checkIco($customer),
            $this->checkDic($customer),
            $this->checkIcDic($customer),
            $this->checkPhone($customer),
            $this->checkEmail($customer),
            $this->checkPostcode($customer),
            $this->checkCompany($customer),
            $this->checkStreet($customer),
        );
    }

    /**
     * Odtlačok kontrolovaných polí — mení sa len vtedy, keď sa naozaj zmenilo
     * niečo, čo kontrola posudzuje. Uloženie kvôli poznámke či statusu tak
     * nevyvolá druhé volanie AI za tie isté údaje.
     */
    public function fingerprint(Customer $customer): string
    {
        $values = [];

        foreach (self::FIELDS as $field) {
            $values[$field] = trim((string) $this->raw($customer, $field));
        }

        return hash('sha256', json_encode($values, JSON_UNESCAPED_UNICODE) ?: '');
    }

    /**
     * Hodnota tak, ako je v databáze — bez castov.
     *
     * Bez toho by kontrola telefónu posudzovala „0 905 411 498", čo je výstup
     * PhoneFormater::get(), a hlásila by medzery, ktoré do stĺpca nikdy nešli.
     * To isté PSČ a IČO.
     */
    public function raw(Customer $customer, string $field): ?string
    {
        $value = $customer->getAttributes()[$field] ?? null;

        return $value === null ? null : (string) $value;
    }

    /** Je hodnota prázdna, alebo len vyzerá vyplnene? */
    public function isBlank(?string $value): bool
    {
        $value = trim((string) $value);

        return $value === '' || in_array(mb_strtolower($value), self::BLANKS, true);
    }

    /** Zoradí nálezy od najzávažnejšieho; v rámci závažnosti podľa poradia polí. */
    public function sort(array $issues): array
    {
        $severity = array_flip(self::SEVERITIES);
        $fields = array_flip(self::FIELDS);

        usort($issues, static function (array $a, array $b) use ($severity, $fields) {
            $bySeverity = ($severity[$b['severity'] ?? ''] ?? 0) <=> ($severity[$a['severity'] ?? ''] ?? 0);

            return $bySeverity !== 0
                ? $bySeverity
                : ($fields[$a['field'] ?? ''] ?? 99) <=> ($fields[$b['field'] ?? ''] ?? 99);
        });

        return $issues;
    }

    // ---------------------------------------------------------------- polia

    private function checkBlank(Customer $customer, string $field): array
    {
        $value = $this->raw($customer, $field);

        if ($value === null) {
            return [];
        }

        $trimmed = trim($value);

        if ($trimmed !== '' && ! in_array(mb_strtolower($trimmed), self::BLANKS, true)) {
            return [];
        }

        // Nula je prázdna hodnota len v daňových číslach — v čísle domu
        // („Ulica 0") by jej zmazanie bola strata údaja.
        if ($trimmed === '0' && ! in_array($field, ['ico', 'dic', 'ic_dic'], true)) {
            return [];
        }

        $nullable = in_array($field, self::NULLABLE, true);

        return [$this->issue(
            $field,
            'warning',
            $trimmed === ''
                ? ($nullable
                    ? 'Pole je uložené ako prázdny reťazec, nie ako prázdna hodnota — filtre „nevyplnené" ho preto nenájdu.'
                    : 'Pole je prázdne, hoci je povinné.')
                : 'Namiesto prázdnej hodnoty je v poli zástupný znak „'.$trimmed.'", ktorý sa vytlačí na faktúru.',
            $value,
            null,
            $nullable ? 'blank_to_null' : null,
        )];
    }

    private function checkWhitespace(Customer $customer, string $field): array
    {
        $value = $this->raw($customer, $field);

        if ($value === null || trim($value) === '') {
            return [];
        }

        $clean = preg_replace('/\s+/u', ' ', trim($value)) ?? $value;

        if ($clean === $value) {
            return [];
        }

        return [$this->issue(
            $field,
            'notice',
            'Hodnota má medzery navyše alebo zalomenie riadku.',
            $value,
            $clean,
            'trim',
        )];
    }

    private function checkIco(Customer $customer): array
    {
        $ico = trim((string) $this->raw($customer, 'ico'));

        if ($this->isBlank($ico)) {
            return [];
        }

        $digits = preg_replace('/\D+/', '', $ico) ?? '';

        if ($digits === '') {
            return [$this->issue('ico', 'error', 'IČO neobsahuje ani jednu číslicu.', $ico)];
        }

        if (strlen($digits) > 8) {
            return [$this->issue('ico', 'error', 'IČO má viac než 8 číslic.', $ico)];
        }

        // Kratšie než 8 číslic nie je chyba v údaji: obce a staré subjekty majú
        // IČO začínajúce nulami a človek ich pri prepise vynechá. Register aj
        // faktúra ho však chcú na 8 miest.
        if ($digits !== $ico || strlen($digits) < 8) {
            $clean = $this->onlySeparators($ico);

            return [$this->issue(
                'ico',
                $clean ? 'warning' : 'error',
                $clean
                    ? 'IČO nie je v tvare, v akom ho pozná register — 8 číslic bez medzier a lomítok.'
                    : 'V poli IČO je okrem čísla aj text.',
                $ico,
                str_pad($digits, 8, '0', STR_PAD_LEFT),
                $clean ? 'ico_pad' : null,
            )];
        }

        if (! $this->icoChecksumValid($digits)) {
            return [$this->issue(
                'ico',
                'error',
                'IČO nesedí na kontrolnú číslicu — takmer isto je v ňom preklep a v registri neexistuje.',
                $ico,
            )];
        }

        return [];
    }

    private function checkDic(Customer $customer): array
    {
        $dic = trim((string) $this->raw($customer, 'dic'));
        $ico = trim((string) $this->raw($customer, 'ico'));

        if ($this->isBlank($dic)) {
            // Chýbajúce DIČ hlásime len tam, kde má čo chýbať — subjekt s IČO
            // ho má vždy. Fyzická osoba bez IČO ho mať nemusí.
            return $this->isBlank($ico)
                ? []
                : [$this->issue(
                    'dic',
                    'warning',
                    'Zákazník má IČO, ale nemá DIČ — na faktúre bude chýbať údaj, ktorý register pozná.',
                    $dic,
                    null,
                    'registry_tax_ids',
                )];
        }

        $digits = preg_replace('/\D+/', '', $dic) ?? '';

        if ($digits !== $dic) {
            // „SK2020610966" je IČ DPH napísané do poľa DIČ; odseknúť „SK"
            // je bezpečné. Čokoľvek iné než predpona a oddeľovače nechávame
            // človeku — mohol by sa stratiť druhý údaj.
            $clean = $this->onlySeparators($dic) || preg_match('/^SK[\s\d]+$/i', $dic) === 1;

            return [$this->issue(
                'dic',
                'warning',
                $clean
                    ? 'DIČ obsahuje medzery alebo predponu SK.'
                    : 'V poli DIČ je okrem čísla aj text.',
                $dic,
                $digits,
                $clean ? 'trim' : null,
            )];
        }

        if (strlen($digits) !== 10) {
            return [$this->issue('dic', 'error', 'DIČ nemá 10 číslic.', $dic)];
        }

        // Slovenské DIČ je deliteľné jedenástimi. V tabuľke to nesedí necelému
        // percentu čísel, takže je to spoľahlivý ukazovateľ preklepu — ale nie
        // dosť na to, aby sme údaj rovno označili za neplatný.
        if (bcmod($digits, '11') !== '0') {
            return [$this->issue(
                'dic',
                'notice',
                'DIČ nesedí na kontrolný súčet — pravdepodobne je v ňom preklep. Overte v registri.',
                $dic,
            )];
        }

        return [];
    }

    private function checkIcDic(Customer $customer): array
    {
        $icDic = trim((string) $this->raw($customer, 'ic_dic'));
        $dic = trim((string) $this->raw($customer, 'dic'));

        if ($this->isBlank($icDic)) {
            return [];
        }

        $normalized = strtoupper(preg_replace('/\s+/', '', $icDic) ?? '');

        if ($normalized !== $icDic) {
            return [$this->issue(
                'ic_dic',
                'notice',
                'IČ DPH má medzery alebo malé písmená.',
                $icDic,
                $normalized,
                'trim',
            )];
        }

        if (! preg_match('/^SK\d{10}$/', $normalized)) {
            return [$this->issue('ic_dic', 'error', 'IČ DPH nie je v tvare SK + 10 číslic.', $icDic)];
        }

        // IČ DPH slovenského platiteľa je vždy „SK" + jeho DIČ. Keď sa
        // rozchádzajú, jedno z tých dvoch čísel patrí niekomu inému.
        if (preg_match('/^\d{10}$/', $dic) && substr($normalized, 2) !== $dic) {
            return [$this->issue(
                'ic_dic',
                'error',
                'IČ DPH sa nezhoduje s DIČ — očakávané SK'.$dic.'.',
                $icDic,
                'SK'.$dic,
            )];
        }

        return [];
    }

    private function checkPhone(Customer $customer): array
    {
        $phone = trim((string) $this->raw($customer, 'phone'));

        if ($this->isBlank($phone)) {
            return [];
        }

        $normalized = $this->normalizePhone($phone);

        if ($normalized === null) {
            return [$this->issue(
                'phone',
                'warning',
                'Telefón sa nedá prečítať ako slovenské ani zahraničné číslo.',
                $phone,
            )];
        }

        if ($normalized === $phone) {
            return [];
        }

        return [$this->issue(
            'phone',
            'notice',
            'Telefón nie je v medzinárodnom tvare — pri exporte a hromadnej pošte sa taký zápis rozpadne.',
            $phone,
            $normalized,
            'phone_format',
        )];
    }

    /**
     * Telefón do tvaru +421XXXXXXXXX.
     *
     * Zámerne odmieta všetko, čo nemá dĺžku telefónneho čísla — časť čísel
     * v tabuľke je prepísaná z pätičky e-mailu aj s poznámkou („0905 111 222
     * kl. 3") a taký zvyšok nemá čo normalizovať; nech ho radšej vidí človek.
     */
    public function normalizePhone(string $phone): ?string
    {
        $value = preg_replace('/[\s\-\/().]+/u', '', $phone) ?? '';

        if ($value === '') {
            return null;
        }

        if (str_starts_with($value, '00')) {
            $value = '+'.substr($value, 2);
        }

        if (str_starts_with($value, '+')) {
            $digits = substr($value, 1);

            return preg_match('/^\d{9,15}$/', $digits) === 1 ? '+'.$digits : null;
        }

        if (preg_match('/^\d+$/', $value) !== 1) {
            return null;
        }

        // 0905123456 → +421905123456
        if (str_starts_with($value, '0') && strlen($value) === 10) {
            return '+421'.substr($value, 1);
        }

        // 905123456 → +421905123456 (človek vynechal vedúcu nulu)
        if (strlen($value) === 9) {
            return '+421'.$value;
        }

        // 421905123456 bez plusu
        if (str_starts_with($value, '421') && strlen($value) === 12) {
            return '+'.$value;
        }

        return null;
    }

    private function checkEmail(Customer $customer): array
    {
        $email = trim((string) $this->raw($customer, 'email'));

        if ($this->isBlank($email)) {
            return [];
        }

        $lower = mb_strtolower($email);

        if ($lower !== $email) {
            return [$this->issue(
                'email',
                'notice',
                'E-mail obsahuje veľké písmená — pri porovnávaní s existujúcim kontaktom z toho vznikajú duplicity.',
                $email,
                $lower,
                'trim',
            )];
        }

        if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return [$this->issue(
                'email',
                'error',
                'E-mail nie je platná adresa — potvrdenie objednávky sa nedoručí.',
                $email,
            )];
        }

        return [];
    }

    private function checkPostcode(Customer $customer): array
    {
        $postcode = trim((string) $this->raw($customer, 'postcode'));

        if ($postcode === '') {
            return [];
        }

        $digits = preg_replace('/\D+/', '', $postcode) ?? '';

        if ($digits === $postcode && strlen($digits) === 5) {
            return [];
        }

        if ($digits !== '' && strlen($digits) <= 5) {
            // Opraviť sa smie len zápis, nie obsah. Keď sa v PSČ okrem medzier
            // a lomítok stratí aj text („04918Lubeník"), je v poli zlepený
            // ďalší údaj a jeho zmazanie by bola strata — to nech vidí človek.
            $onlySeparators = preg_match('/^[\d\s\/.-]+$/u', $postcode) === 1;

            return [$this->issue(
                'postcode',
                $onlySeparators ? 'notice' : 'warning',
                $onlySeparators
                    ? 'PSČ nie je uložené ako 5 číslic bez medzier.'
                    : 'V PSČ je zlepený aj iný údaj — skontrolujte, čo do poľa patrí.',
                $postcode,
                str_pad($digits, 5, '0', STR_PAD_LEFT),
                $onlySeparators ? 'postcode_format' : null,
            )];
        }

        return [$this->issue('postcode', 'warning', 'PSČ nemá 5 číslic.', $postcode)];
    }

    private function checkCompany(Customer $customer): array
    {
        $company = trim((string) $this->raw($customer, 'company'));

        if ($company === '') {
            return [];
        }

        // Do „Názov firmy" sa občas prepíše IČO — formulár má obe polia pod
        // sebou a pri kopírovaní z hlavičky e-mailu je zámena na jeden klik.
        if (preg_match('/^\d{6,8}$/', $company) === 1) {
            return [$this->issue(
                'company',
                'error',
                'V názve firmy je číslo, nie názov — vyzerá to na IČO zapísané do zlého poľa.',
                $company,
            )];
        }

        // Adresa vlepená do názvu („Základná škola, Komenského 959, Senica").
        // Na faktúre potom stojí ulica dvakrát a zakaždým v inom tvare.
        if (substr_count($company, ',') >= 2 || preg_match('/,\s*\d{3}\s?\d{2}\b/u', $company) === 1) {
            return [$this->issue(
                'company',
                'warning',
                'V názve firmy je aj adresa — na faktúre sa potom vytlačí dvakrát.',
                $company,
            )];
        }

        return [];
    }

    private function checkStreet(Customer $customer): array
    {
        $street = trim((string) $this->raw($customer, 'street'));

        if ($street === '') {
            return [];
        }

        // Samotné číslo bez názvu ulice. V malých obciach je to bežné a správne
        // („Bidovce 210"), preto len upozornenie a s návrhom doplniť obec.
        if (preg_match('/^[\d\/\-]+$/', $street) === 1) {
            $city = trim((string) $this->raw($customer, 'city'));

            return [$this->issue(
                'street',
                'notice',
                'V ulici je iba číslo. Pri obci bez ulíc je to v poriadku, ale na obálke chýba názov.',
                $street,
                $city !== '' ? $city.' '.$street : null,
            )];
        }

        return [];
    }

    // ------------------------------------------------------------ pomocníci

    /**
     * Kontrolná číslica slovenského IČO: váhy 8..2 nad prvými siedmimi
     * číslicami, zvyšok po delení jedenástimi. Preklep cez ňu prejde len
     * výnimočne — nález z tohto pravidla je takmer vždy skutočná chyba.
     */
    public function icoChecksumValid(string $ico): bool
    {
        if (preg_match('/^\d{8}$/', $ico) !== 1) {
            return false;
        }

        $sum = 0;

        foreach ([8, 7, 6, 5, 4, 3, 2] as $index => $weight) {
            $sum += $weight * (int) $ico[$index];
        }

        $remainder = $sum % 11;
        $check = match ($remainder) {
            0 => 1,
            1 => 0,
            default => 11 - $remainder,
        };

        return $check === (int) $ico[7];
    }

    /**
     * Je v hodnote okrem číslic len to, čo sa smie zahodiť?
     *
     * Automatická oprava číselného poľa smie zmeniť zápis, nie obsah. Keď je
     * v poli aj text, je tam zlepený druhý údaj a jeho tiché zmazanie by bola
     * strata — taký nález patrí človeku.
     */
    private function onlySeparators(string $value): bool
    {
        return preg_match('/^[\d\s\/.-]+$/u', $value) === 1;
    }

    private function issue(
        string $field,
        string $severity,
        string $message,
        ?string $current = null,
        ?string $suggested = null,
        ?string $fix = null,
    ): array {
        return [
            'field' => $field,
            'severity' => $severity,
            'source' => 'rule',
            'message' => $message,
            'current' => $current,
            'suggested' => $suggested,
            'fix' => $fix,
        ];
    }
}
