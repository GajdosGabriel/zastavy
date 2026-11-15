<?php

namespace App\Support;

class Csv
{
    /**
     * Bunka bezpečná pre Excel.
     *
     * Názov firmy či poznámku píše do košíka ktokoľvek z internetu. Hodnotu,
     * ktorá sa začína na `=`, `@`, `+` alebo `-`, tabuľkový procesor vyhodnotí
     * ako vzorec (CSV injection) — apostrof na začiatku z nej spraví text.
     * Čísla a telefóny („+421 905 123 456", „-12,50") ostávajú bez zmeny.
     */
    public static function cell(?string $value): string
    {
        $value = (string) $value;

        if ($value === '' || ! in_array($value[0], ['=', '@', '+', '-', "\t", "\r"], true)) {
            return $value;
        }

        if (preg_match('/^[+-][\d\s().,\/-]*$/', $value)) {
            return $value;
        }

        return "'".$value;
    }
}
