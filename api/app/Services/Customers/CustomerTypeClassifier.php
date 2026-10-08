<?php

namespace App\Services\Customers;

use App\Enums\CustomerType;

/**
 * Určí druh zákazníka z toho, čo o ňom vieme pri uložení.
 *
 * Len pravidlá, žiadna sieť ani AI — beží pri každom novom zákazníkovi vrátane
 * posledného kroku objednávky. Poradie otázok je zámerné:
 *
 *   1. škola   — „Obec Kunerad Materská škola" je škôlka, hoci začína obcou,
 *                a „Súkromná škola, s.r.o." je škola, hoci končí ako firma;
 *   2. firma   — právna forma v názve („Obecný podnik služieb, s.r.o.");
 *   3. obec    — obec, mesto, mestská časť a ich úrady, alebo IČO z bloku obcí;
 *   4. osoba   — bez IČO a názov je meno kontaktnej osoby (tak ho ukladá
 *                checkout pri voľbe „Súkromná osoba");
 *   5. firma   — všetko ostatné: farnosti, združenia, zbory, živnostníci.
 *
 * Výsledok je odhad a v administrácii sa dá prepísať; už nastavený typ
 * klasifikátor nikdy nemení.
 */
class CustomerTypeClassifier
{
    private const TITLES = [
        'mgr', 'ing', 'bc', 'phdr', 'paeddr', 'judr', 'mudr', 'mvdr', 'rndr', 'thdr', 'thlic',
        'dr', 'phd', 'mba', 'doc', 'prof', 'arch', 'csc', 'dis', 'art', 'p', 'pani', 'pan',
    ];

    /**
     * Slová, ktoré v mene človeka nebývajú. Kontakt si do mena občas skopíruje
     * názov organizácie („Hasiči Lúčka" / „Hasiči Lúčka") a zhoda názvu
     * s menom by z nej bez tohto spravila súkromnú osobu.
     */
    private const ORGANIZATION_WORDS = '/\b(urad|zbor|zvaz|klub|club|zdruzenie|farnost|spolok|organizacia|centrum|dom|domov|sluzby|jednota|oz|dhz|tj|fk|sk|team|hasic\w*|cirk\w+|nadacia|kniznica|muzeum|stredisko|sprava|podnik|druzstvo|hotel|penzion|restauracia|obchod|shop|studio|agentura|sekretariat|uctaren|starosta)\b/';

    public function classify(?string $company, ?string $ico = null, ?string $contactName = null): CustomerType
    {
        $name = $this->normalize($company);

        if ($this->isSchool($name)) {
            return CustomerType::School;
        }

        if ($this->hasLegalForm($name)) {
            return CustomerType::Company;
        }

        if ($this->isMunicipality($name) || $this->hasMunicipalIco($ico)) {
            return CustomerType::Municipality;
        }

        if (preg_match('/^(sukromna|fyzicka) osoba$/', $name) === 1) {
            return CustomerType::Person;
        }

        if (! $this->hasIco($ico) && $this->isContactName($name, $this->normalize($contactName))) {
            return CustomerType::Person;
        }

        return CustomerType::Company;
    }

    private function isSchool(string $name): bool
    {
        return preg_match(
            '/skol|gymnazi|univerzit|iskola|ovoda|konzervatori|l[iy]ceum|uciliste|akademia|fakulta|internat(?!ion)'
            .'|centrum volneho casu|pedagogicko psychologick|reedukacn|liecebno vychovn|diagnosticke centrum'
            .'|rodic\w* zdruzenie|zdruzenie rodicov|rada rodicov'
            .'|\b(zs\w*|ms|zus|szus|cvc|zrps|rz|cpppap|cppp|czs|cms|sms|szs|cszs|oa|soa|sou|gym|gymn|ms(s|a)zs|sos\w*|ssos\w*|sps\w{0,3})\b/',
            $name
        ) === 1;
    }

    private function hasLegalForm(string $name): bool
    {
        return preg_match('/\b(s r o|sro|spol|a s|k s|v o s|j s a|s p|gmbh|ltd|kft)\b/', $name) === 1;
    }

    private function isMunicipality(string $name): bool
    {
        return preg_match(
            '/^(obec|mesto|mestecko|hlavne mesto|mc|ocu|ou|msu|mu|miu)\b'
            .'|\bmestska cast\b|\b(obecny|mestsky|miestny) urad\b|\bmagistrat\b/',
            $name
        ) === 1;
    }

    /**
     * IČO z bloku, ktorý dostali obce a mestá (00303xxx – 00332xxx).
     *
     * Zachráni riadky, kde názov nepovie nič („Ratkovská Suchá", číslo
     * v názve). Školy s IČO zriaďovateľa sem neprepadnú — tie zachytí názov.
     */
    private function hasMunicipalIco(?string $ico): bool
    {
        $digits = preg_replace('/\D+/', '', (string) $ico) ?? '';

        if ($digits === '' || strlen($digits) > 8) {
            return false;
        }

        return (int) $digits >= 303000 && (int) $digits <= 332999;
    }

    private function hasIco(?string $ico): bool
    {
        return (int) preg_replace('/\D+/', '', (string) $ico) !== 0;
    }

    /** Je názov zákazníka menom jeho kontaktnej osoby (alebo chýba úplne)? */
    private function isContactName(string $name, string $contact): bool
    {
        if ($name === '') {
            return true;
        }

        if (preg_match('/\d/', $name) === 1 || preg_match(self::ORGANIZATION_WORDS, $name) === 1) {
            return false;
        }

        $nameTokens = $this->nameTokens($name);
        $contactTokens = $this->nameTokens($contact);

        if ($nameTokens === [] || $contactTokens === [] || count($nameTokens) > 3) {
            return false;
        }

        // „Jozef Novák" / „Novák" aj „Novák" / „Novák Jozef" je ten istý človek.
        return array_diff($nameTokens, $contactTokens) === [] || array_diff($contactTokens, $nameTokens) === [];
    }

    /** @return array<int, string> */
    private function nameTokens(string $value): array
    {
        return array_values(array_unique(array_filter(
            explode(' ', $value),
            fn (string $token) => mb_strlen($token) > 1 && ! in_array($token, self::TITLES, true),
        )));
    }

    private function normalize(?string $value): string
    {
        $value = mb_strtolower(trim((string) $value));
        $value = strtr($value, [
            'á' => 'a', 'ä' => 'a', 'č' => 'c', 'ď' => 'd', 'é' => 'e', 'ě' => 'e', 'í' => 'i', 'ĺ' => 'l', 'ľ' => 'l',
            'ň' => 'n', 'ó' => 'o', 'ô' => 'o', 'ö' => 'o', 'ő' => 'o', 'ŕ' => 'r', 'ř' => 'r', 'š' => 's', 'ť' => 't',
            'ú' => 'u', 'ů' => 'u', 'ü' => 'u', 'ű' => 'u', 'ý' => 'y', 'ž' => 'z', 'ś' => 's', 'ź' => 'z',
        ]);

        return trim(preg_replace('/[^a-z0-9]+/', ' ', $value) ?? '');
    }
}
