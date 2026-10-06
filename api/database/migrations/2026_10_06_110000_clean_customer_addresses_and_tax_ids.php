<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// Čistenie adries, IČO a DIČ zákazníkov. Mení sa len to, čo je podložené inými poľami
// toho istého záznamu (ulica, mesto, PSČ) alebo má jediný možný zápis:
//   - adresa v názve firmy („Základná škola, Hlavná 5, Nitra") sa z názvu odstráni, ak ju
//     potvrdzujú polia ulica/mesto/PSČ (alebo je ulica prázdna a mesto s PSČ sedia),
//   - v meste ostane len mesto (bez PSČ, čísla domu, okresu, kraja, „IČO: …"); číslo domu
//     sa presunie do ulice, ak tam chýba, a samotné číslo v ulici sa doplní o obec,
//   - IČO/DIČ z textu v meste alebo názve sa doplnia len do prázdneho poľa a len ak sú
//     platné (kontrolná číslica IČO, DIČ deliteľné 11),
//   - DIČ sa zbaví medzier a predpôn „SK"/„DIČ:", nehodnoty („Slovensko", „X", „0") sa vyprázdnia,
//   - chýbajúce DIČ sa prevezme z iného zákazníka s rovnakým IČO, ak je jednoznačné.
// DIČ sa z IČO odvodiť nedá, preto zákazníci, ktorí ho nemajú nikde, ostávajú bez neho.
// Idempotentné — druhé spustenie už nenájde čo meniť.
return new class extends Migration
{
    private const FIELDS = ['company', 'street', 'city', 'postcode', 'ico', 'dic', 'ic_dic'];

    private const STOPWORDS = ['ul', 'ulica', 'c', 'd', 'cislo', 'c d'];

    private const MAIL_DISTRICTS = ['bratislava', 'kosice', 'zilina', 'povazska bystrica', 'trencin', 'nitra', 'presov', 'banska bystrica'];

    public function up(): void
    {
        if (! Schema::hasTable('customers')) {
            return;
        }

        $changes = $this->changes();

        foreach ($changes as $id => $fields) {
            DB::table('customers')->where('id', $id)->update(array_map(fn (array $pair) => $pair[1], $fields));
        }

        // Posudok kontroly kvality platí pre staré údaje — nech sa prepočíta v najbližšom behu.
        if ($changes !== [] && Schema::hasTable('customer_reviews')) {
            DB::table('customer_reviews')->whereIn('customer_id', array_keys($changes))->update([
                'due_at' => now(),
                'resolved_at' => null,
                'resolved_by' => null,
            ]);
        }
    }

    public function down(): void
    {
        // Opravy dát sa nevracajú.
    }

    /**
     * Zmeny, ktoré by sa vykonali: [id => [pole => [pôvodná, nová]]].
     *
     * @return array<int, array<string, array{0: ?string, 1: ?string}>>
     */
    public function changes(): array
    {
        $changes = [];

        DB::table('customers')->whereNull('deleted_at')->orderBy('id')->chunkById(500, function ($rows) use (&$changes) {
            foreach ($rows as $row) {
                $old = [];
                foreach (self::FIELDS as $field) {
                    $old[$field] = $row->{$field} === null ? null : (string) $row->{$field};
                }

                $new = $this->clean($old);
                $diff = [];

                foreach (self::FIELDS as $field) {
                    if ($new[$field] !== $old[$field]) {
                        $diff[$field] = [$old[$field], $new[$field]];
                    }
                }

                if ($diff !== []) {
                    $changes[$row->id] = $diff;
                }
            }
        });

        // DIČ z iného zákazníka s rovnakým IČO — len keď je jediný možný.
        $known = [];
        foreach (DB::table('customers')->whereNull('deleted_at')->whereRaw("dic REGEXP '^[0-9]{10}$'")->get(['id', 'ico', 'dic']) as $row) {
            $known[$row->ico][$row->dic] = true;
        }

        foreach (DB::table('customers')->whereNull('deleted_at')->whereRaw("ico REGEXP '^[0-9]{8}$'")->get(['id', 'ico', 'dic']) as $row) {
            $current = $changes[$row->id]['dic'][1] ?? $row->dic;

            if (($current === null || trim((string) $current) === '') && isset($known[$row->ico]) && count($known[$row->ico]) === 1) {
                $changes[$row->id]['dic'] = [$row->dic, array_key_first($known[$row->ico])];
            }
        }

        return $changes;
    }

    /**
     * @param  array<string, ?string>  $c
     * @return array<string, ?string>
     */
    private function clean(array $c): array
    {
        foreach (['company', 'street', 'city'] as $field) {
            $c[$field] = $this->squish($c[$field]);
        }

        if ($c['street'] !== null && preg_match('/^[\-–—_.\s]+$/u', $c['street'])) {
            $c['street'] = null;
        }

        $c['ico'] = $this->cleanIco($c['ico']);
        $c = $this->cleanDic($c);
        $c = $this->cleanCity($c);
        $c = $this->cleanCompany($c);

        // Po vyčistení mohlo ostať prázdno.
        foreach (['company', 'street'] as $field) {
            if ($c[$field] === '') {
                $c[$field] = null;
            }
        }

        return $c;
    }

    private function squish(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = trim(preg_replace('/[\s\x{00A0}]+/u', ' ', $value) ?? '');

        return $value;
    }

    // ---- IČO / DIČ ---------------------------------------------------------------

    private function cleanIco(?string $ico): ?string
    {
        if ($ico === null || $ico === '') {
            return $ico;
        }

        if (preg_match('/^\d{8}$/', $ico)) {
            return $ico;
        }

        $digits = preg_replace('/\D/', '', $ico) ?? '';

        if ($digits === '' || (int) $digits === 0) {
            return preg_match('/\d/', $ico) ? $ico : null;
        }

        $trimmed = ltrim($digits, '0');

        if (strlen($trimmed) <= 8) {
            $padded = str_pad($trimmed, 8, '0', STR_PAD_LEFT);

            if ($this->validIco($padded)) {
                return $padded;
            }
        }

        return $ico;
    }

    private function validIco(string $ico): bool
    {
        if (! preg_match('/^\d{8}$/', $ico)) {
            return false;
        }

        $sum = 0;
        for ($i = 0; $i < 7; $i++) {
            $sum += (int) $ico[$i] * (8 - $i);
        }

        $check = 11 - ($sum % 11);
        $check = $check === 10 ? 0 : ($check === 11 ? 1 : $check);

        return $check === (int) $ico[7];
    }

    private function validDic(string $dic): bool
    {
        return preg_match('/^[1-9]\d{9}$/', $dic) === 1 && (int) $dic % 11 === 0;
    }

    /** @param  array<string, ?string>  $c */
    private function cleanDic(array $c): array
    {
        $dic = $c['dic'];

        if ($dic !== null && $dic !== '') {
            $value = trim($dic);

            if (! preg_match('/[1-9]/', $value)) {
                $value = '';
            } elseif (preg_match('/^(sk)\s*([\d\s]+)$/i', $value, $m)) {
                $digits = preg_replace('/\s+/', '', $m[2]);
                if (strlen($digits) === 10 && ($c['ic_dic'] === null || $c['ic_dic'] === '')) {
                    $c['ic_dic'] = 'SK'.$digits;
                }
                $value = $digits;
            } elseif (preg_match('/^di[čc]\s*[:.]?\s*([\d\s]+)$/iu', $value, $m)) {
                $value = preg_replace('/\s+/', '', $m[1]);
            } elseif (preg_match('/^[\d\s]+$/', $value)) {
                $value = preg_replace('/\s+/', '', $value);
            }

            $c['dic'] = $value === '' ? null : $value;
        }

        $icDic = $c['ic_dic'];
        if ($icDic !== null && $icDic !== '') {
            if (! preg_match('/\d/', $icDic)) {
                $c['ic_dic'] = null;
            }
        }

        if (($c['dic'] === null || $c['dic'] === '') && $c['ic_dic'] !== null && preg_match('/^SK(\d{10})$/i', $c['ic_dic'], $m)) {
            $c['dic'] = $m[1];
        }

        return $c;
    }

    /** Doplní IČO/DIČ nájdené v texte, ale len do prázdneho poľa a len ak je platné. */
    private function harvest(array $c, string $text): array
    {
        if (preg_match('/(?<!\p{L})(?:IČO|ICO)(?!\p{L})\s*[:.]?\s*([\d\s.]*\d)/iu', $text, $m)) {
            $candidate = $this->cleanIco(preg_replace('/[\s.]+/', '', $m[1]));

            if (($c['ico'] === null || $c['ico'] === '') && $candidate !== null && $this->validIco($candidate)) {
                $c['ico'] = $candidate;
            }
        }

        if (preg_match('/(?<!\p{L})DIČ(?!\p{L})\s*[:.]?\s*(\d[\d\s]*\d)/u', $text, $m)) {
            $candidate = preg_replace('/\s+/', '', $m[1]);

            if (($c['dic'] === null || $c['dic'] === '') && $this->validDic($candidate)) {
                $c['dic'] = $candidate;
            }
        }

        return $c;
    }

    // ---- mesto, ulica, PSČ -------------------------------------------------------

    /** @param  array<string, ?string>  $c */
    private function cleanCity(array $c): array
    {
        $city = (string) $c['city'];

        if ($city === '') {
            return $this->cityFromStreet($c);
        }

        // Preklep v PSČ: písmeno O namiesto nuly.
        if ($c['postcode'] !== null && preg_match('/^[O0-9]{5}$/i', $c['postcode']) && preg_match('/O/i', $c['postcode'])) {
            $c['postcode'] = str_ireplace('O', '0', $c['postcode']);
        }

        // Veta z objednávky namiesto mesta.
        if (preg_match('/dobr[ýy] de[nň]|objedn[aá]vame/iu', $city)) {
            $c['city'] = '';

            return $this->cityFromStreet($c);
        }

        if (preg_match('/(?<!\p{L})(?:IČO|ICO|DIČ)(?!\p{L})/iu', $city)) {
            $c = $this->harvest($c, $city);
            $city = $this->squish(preg_replace('/[\s,;:]*(?<!\p{L})(?:IČO|ICO|DIČ)(?!\p{L}).*$/iu', '', $city) ?? '') ?? '';
        }

        // PSČ na začiatku alebo na konci.
        if (preg_match('/^(\d{3}\s?\d{2})[\s,]+(.+)$/u', $city, $m)) {
            $c = $this->fillPostcode($c, $m[1]);
            $city = $m[2];
        } elseif (preg_match('/^(.+?)[\s,]+(\d{3}\s?\d{2})$/u', $city, $m)) {
            $c = $this->fillPostcode($c, $m[2]);
            $city = $m[1];
        }

        // „Mesto, okres X, Y kraj, Slovakia" → „Mesto".
        if (str_contains($city, ',')) {
            $parts = array_map('trim', explode(',', $city));
            $first = array_shift($parts);
            $rest = array_filter($parts, fn (string $p) => $p !== '');

            if ($first !== '' && $rest !== [] && ! array_filter($rest, fn (string $p) => ! preg_match('/okres|okr\.|kraj|slovak|slovensk|^[\p{L}]+(?:ský|cký|ský)$/iu', $p))) {
                $city = $first;
            }
        }

        $city = preg_replace('/^(?:obec|mesto)\s+/iu', '', $city) ?? $city;
        $c['city'] = $this->squish($city) ?? '';

        return $this->cityNumber($c);
    }

    /** @param  array<string, ?string>  $c */
    private function fillPostcode(array $c, string $postcode): array
    {
        if ($c['postcode'] === null || ! preg_match('/^\d{5}$/', $c['postcode'])) {
            $c['postcode'] = preg_replace('/\s+/', '', $postcode);
        }

        return $c;
    }

    /** Mesto prázdne, ale ulica je v tvare „Ulica 22, Mesto". */
    private function cityFromStreet(array $c): array
    {
        if ($c['street'] !== null && preg_match('/^(.*\d[\w\/]*),\s*(\p{L}[\p{L} \-]+)$/u', $c['street'], $m)) {
            $c['street'] = trim($m[1]);
            $c['city'] = trim($m[2]);
        }

        return $c;
    }

    /** „Sedliská 85" v meste → mesto „Sedliská", číslo patrí do ulice. */
    private function cityNumber(array $c): array
    {
        $city = (string) $c['city'];

        if (preg_match('/^(.*?[^\s,\d])[\s,]+(?:č\.?\s*)?(\d+[A-Za-z]?(?:\s*\/\s*\d+[A-Za-z]?)?)$/u', $city, $m)) {
            [$name, $number] = [trim($m[1]), preg_replace('/\s+/', '', $m[2])];
            $street = (string) $c['street'];
            $streetBlank = $street === '';

            if (preg_match('/\d/', $name) || in_array($this->normalize($name), self::MAIL_DISTRICTS, true)) {
                return $this->bareStreetNumber($c);
            }

            if ($streetBlank) {
                $c['street'] = $name.' '.$number;
                $c['city'] = $name;
            } elseif (preg_match('/\b'.preg_quote(preg_replace('/\/.*/', '', $number), '/').'\b/', $street) || $this->normalize($street) === $this->normalize($name)) {
                if (! preg_match('/\d/', $street)) {
                    $c['street'] = $street.' '.$number;
                }
                $c['city'] = $name;
            }
        }

        return $this->bareStreetNumber($c);
    }

    /**
     * Ulica je len číslo domu a mesto je obec: „442" + „Rudina" → „Rudina 442".
     * Len keď sa obec spomína aj v názve — pri mestách by to bola vymyslená adresa.
     */
    private function bareStreetNumber(array $c): array
    {
        $city = (string) $c['city'];

        if ($c['street'] !== null && $city !== '' && ! preg_match('/\d/', $city)
            && $this->tokensIn($this->tokens($city), $this->tokens((string) $c['company']))
            && preg_match('/^(?:č\.?\s*(?:d\.?)?\s*)?(\d+[A-Za-z]?(?:\/\d+[A-Za-z]?)?)$/u', $c['street'], $m)) {
            $c['street'] = $city.' '.$m[1];
        }

        return $c;
    }

    // ---- názov firmy s adresou ---------------------------------------------------

    /** @param  array<string, ?string>  $c */
    private function cleanCompany(array $c): array
    {
        $company = (string) $c['company'];

        if ($company === '' || str_starts_with($company, 'Nezistená organizácia')) {
            return $c;
        }

        if (str_contains($company, ',')) {
            $result = $this->stripCommaTail($c, $company);
        } else {
            $result = $this->stripSuffix($c, $company);
        }

        return $result ?? $c;
    }

    /** @return ?array<string, ?string> */
    private function stripCommaTail(array $c, string $company): ?array
    {
        $parts = array_map('trim', explode(',', $company));
        $head = array_shift($parts);
        $parts = array_values(array_filter($parts, fn (string $p) => $p !== ''));

        if (mb_strlen($head) < 3 || $parts === []) {
            return $head !== '' && mb_strlen($head) >= 3 && $parts === [] ? array_merge($c, ['company' => $head]) : null;
        }

        $city = (string) $c['city'];
        $street = (string) $c['street'];
        $matchedStreet = false;
        $corroborated = false;
        $candidateStreet = null;
        $found = $c;

        foreach ($parts as $part) {
            if (preg_match('/^(?:IČO|ICO)(?!\p{L})/iu', $part)) {
                $found = $this->harvest($found, $part);
                if (! preg_match('/^(?:IČO|ICO)\s*[:.]?\s*[\d\s.]+$/iu', $part)) {
                    return null;
                }

                continue;
            }

            $rest = trim(preg_replace('/\b\d{3}\s?\d{2}\b/u', '', $part) ?? '');
            $hadPostcode = $rest !== $part;

            if ($rest === '' && $hadPostcode) {
                $corroborated = $corroborated || ($c['postcode'] !== null && preg_replace('/\s+/', '', $part) === $c['postcode']);

                continue;
            }

            if ($city !== '' && $this->fuzzyEqual($rest, $city)) {
                $corroborated = true;

                continue;
            }

            if (preg_match('/\d/', $rest)) {
                if ($street !== '' && $this->fuzzyContains($rest, $street)) {
                    $matchedStreet = true;
                    $corroborated = true;

                    continue;
                }

                if ($street === '' && $candidateStreet === null && ! $hadPostcode) {
                    $candidateStreet = $rest;

                    continue;
                }
            }

            return null;
        }

        if ($candidateStreet !== null) {
            if (! $corroborated) {
                return null;
            }
            $found['street'] = $candidateStreet;
        } elseif (! $matchedStreet && ! $corroborated) {
            return null;
        }

        $found['company'] = rtrim($head, " -–");

        // „MŠ Rumanova 4, Košice" — po odrezaní mesta ostala ulica v samotnom názve.
        return $this->stripSuffix($found, $found['company']) ?? $found;
    }

    /** „Základná škola Jelenecká 72 95101 Nitrianske Hrnčiarovce" → „Základná škola". */
    private function stripSuffix(array $c, string $company): ?array
    {
        $street = (string) $c['street'];
        $city = (string) $c['city'];

        if ($street === '' || ! preg_match('/\d/', $street)) {
            return null;
        }

        $words = explode(' ', $company);
        $variants = array_filter([
            $this->normalize($street),
            $this->normalize($street.' '.$city),
            $this->normalize($street.' '.$c['postcode'].' '.$city),
            $this->normalize($street.' '.$c['postcode']),
        ]);

        for ($i = count($words) - 1; $i >= 2; $i--) {
            $suffix = $this->normalize(implode(' ', array_slice($words, $i)));

            if (in_array($suffix, $variants, true)) {
                return array_merge($c, ['company' => rtrim(implode(' ', array_slice($words, 0, $i)), " -–,")]);
            }
        }

        return null;
    }

    // ---- porovnávanie ------------------------------------------------------------

    private function normalize(?string $value): string
    {
        $value = mb_strtolower(trim((string) $value));
        $value = strtr($value, [
            'á' => 'a', 'ä' => 'a', 'č' => 'c', 'ď' => 'd', 'é' => 'e', 'ě' => 'e', 'í' => 'i', 'ĺ' => 'l', 'ľ' => 'l',
            'ň' => 'n', 'ó' => 'o', 'ô' => 'o', 'ö' => 'o', 'ő' => 'o', 'ŕ' => 'r', 'ř' => 'r', 'š' => 's', 'ť' => 't',
            'ú' => 'u', 'ů' => 'u', 'ü' => 'u', 'ű' => 'u', 'ý' => 'y', 'ž' => 'z',
        ]);

        return trim(preg_replace('/[^a-z0-9]+/', ' ', $value) ?? '');
    }

    /** @return array<int, string> */
    private function tokens(string $value): array
    {
        return array_values(array_filter(
            explode(' ', $this->normalize($value)),
            fn (string $t) => $t !== '' && ! in_array($t, self::STOPWORDS, true),
        ));
    }

    private function fuzzyToken(string $a, string $b): bool
    {
        if ($a === $b) {
            return true;
        }

        $len = min(strlen($a), strlen($b));

        return $len >= 4 && ! ctype_digit($a) && levenshtein($a, $b) <= ($len >= 8 ? 2 : 1);
    }

    /** Všetky tokeny `$a` sa nachádzajú v `$b`. */
    private function tokensIn(array $a, array $b): bool
    {
        if ($a === []) {
            return false;
        }

        foreach ($a as $token) {
            if (! array_filter($b, fn (string $other) => $this->fuzzyToken($token, $other))) {
                return false;
            }
        }

        return true;
    }

    private function fuzzyEqual(string $a, string $b): bool
    {
        $ta = $this->tokens($a);
        $tb = $this->tokens($b);

        return $this->tokensIn($ta, $tb) && $this->tokensIn($tb, $ta);
    }

    /** Jedno je podmnožinou druhého (ulica „Sasinkova 530/2" vs. „Sasinkova 530"). */
    private function fuzzyContains(string $part, string $street): bool
    {
        $tp = $this->tokens($part);
        $ts = $this->tokens($street);

        return $this->tokensIn($tp, $ts) || $this->tokensIn($ts, $tp);
    }
};
