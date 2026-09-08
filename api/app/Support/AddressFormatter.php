<?php

namespace App\Support;

use Illuminate\Support\Str;

/**
 * Tvar adresných hodnôt — jedno miesto pre uloženie aj pre zobrazenie.
 *
 * Casty na `Customer` (PostCodeFormater, PhoneFormater) robia to isté, ale
 * padajú na null a nedajú sa použiť na poli, ktoré nemusí byť vyplnené.
 * Doručovacia adresa býva prázdna častejšie než vyplnená, tak radšej funkcie.
 */
class AddressFormatter
{
    /** PSČ do databázy: bez medzier, doplnené na päť číslic. */
    public static function normalizePostcode(?string $value): ?string
    {
        $digits = preg_replace('/\D+/', '', (string) $value);

        if ($digits === '') {
            return null;
        }

        return str_pad(substr($digits, 0, 5), 5, '0', STR_PAD_LEFT);
    }

    /** PSČ na obálku: „94901" → „949 01". */
    public static function formatPostcode(?string $value): ?string
    {
        $digits = preg_replace('/\D+/', '', (string) $value);

        if ($digits === '') {
            return null;
        }

        return strlen($digits) === 5
            ? substr($digits, 0, 3).' '.substr($digits, 3)
            : $digits;
    }

    /** Telefón do databázy: bez medzier, prázdny reťazec je null. */
    public static function normalizePhone(?string $value): ?string
    {
        $cleaned = Str::remove(' ', trim((string) $value));

        return $cleaned === '' ? null : $cleaned;
    }

    /** Orezaný text, prázdny reťazec je null. */
    public static function normalizeText(?string $value, int $limit): ?string
    {
        $trimmed = trim((string) $value);

        return $trimmed === '' ? null : mb_substr($trimmed, 0, $limit);
    }

    /**
     * Kľúč na porovnanie dvoch adries — či ide o tú istú, bez ohľadu na
     * medzery, veľké písmená a diakritiku v zápise.
     */
    public static function fingerprint(?string ...$parts): string
    {
        return collect($parts)
            ->map(fn ($part) => Str::of((string) $part)->ascii()->lower()->replaceMatches('/[^a-z0-9]+/', ' ')->trim()->toString())
            ->filter()
            ->implode('|');
    }
}
