<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// Predošlá oprava dát (clean_up_data_quality_issues) prepísala telefóny s jedným
// číslom a zoznamy nechala človeku. Tu sa riešia práve tie: „045/6812371,0910324350",
// „056/6286125,Mobil:0911910564", dve čísla zlepené bez oddeľovača.
//
// Stĺpec unesie jedno číslo. Ostáva v ňom mobil (naň sa dovolá kuriér aj obchod),
// ostatné čísla sa presunú do internej poznámky — nič sa nestráca.
//
// Riadok, v ktorom sa nedá prečítať hoci len jedno z čísel („0918", „Jana",
// „0301/5581194"), sa nemení vôbec. Idempotentné. Objednávky sa nemenia:
// telefón v nich je historický snapshot.
return new class extends Migration
{
    private const NOTE_LABEL = 'Ďalší telefón: ';

    /** Tabuľka => dĺžka poznámky (null = text bez limitu). */
    private const TABLES = [
        'users' => null,
        'customers' => 255,
    ];

    public function up(): void
    {
        foreach (self::TABLES as $table => $noteLimit) {
            if (! Schema::hasTable($table) || ! Schema::hasColumn($table, 'phone') || ! Schema::hasColumn($table, 'note')) {
                continue;
            }

            DB::table($table)
                ->whereNotNull('phone')
                ->where('phone', '!=', '')
                ->whereRaw("phone NOT REGEXP '^[+][0-9]{9,15}$'")
                ->orderBy('id')
                ->chunkById(500, function ($rows) use ($table, $noteLimit) {
                    foreach ($rows as $row) {
                        $this->rewrite($table, $row, $noteLimit);
                    }
                });
        }
    }

    public function down(): void
    {
        // Opravy dát sa nevracajú.
    }

    private function rewrite(string $table, object $row, ?int $noteLimit): void
    {
        $numbers = $this->parse($row->phone);

        if ($numbers === null) {
            return;
        }

        // Mobil má prednosť; bez mobilu ostáva prvé číslo v poradí.
        $primary = collect($numbers)->first(fn ($number) => str_starts_with($number, '+4219')) ?? $numbers[0];
        $others = array_values(array_diff($numbers, [$primary]));

        $values = ['phone' => $primary];

        if ($others !== []) {
            $note = trim((string) $row->note);
            $addition = self::NOTE_LABEL.implode(', ', $others);
            $note = $note === '' ? $addition : $note.' | '.$addition;

            // Radšej nechať riadok človeku, než mu poznámku useknúť.
            if ($noteLimit !== null && mb_strlen($note) > $noteLimit) {
                return;
            }

            $values['note'] = $note;
        }

        DB::table($table)->where('id', $row->id)->update($values);
    }

    /**
     * Rozoberie hodnotu na čísla v tvare +421XXXXXXXXX.
     *
     * @return array<int, string>|null null = aspoň jedna časť sa nedá prečítať
     */
    private function parse(string $raw): ?array
    {
        $parts = preg_split('/[,;]+/u', $raw, -1, PREG_SPLIT_NO_EMPTY) ?: [];

        // 05447921280911797631 — dve desaťmiestne čísla zlepené bez oddeľovača.
        if (count($parts) === 1 && preg_match('/^(0\d{9})(0\d{9})$/', trim($parts[0]), $m) === 1) {
            $parts = [$m[1], $m[2]];
        }

        $numbers = [];
        $areaCode = null;

        foreach ($parts as $part) {
            // „Mobil:0911910564", „mobil0907929585" — popis pred číslom.
            $part = preg_replace('/^\s*(mobil|mob|tel|fax)[.:\s]*/iu', '', trim($part)) ?? '';

            // „056/6761114,6761117" — druhé číslo je z tej istej ústredne, predvoľba sa neopakuje.
            if ($areaCode !== null && preg_match('/^\d{6,7}$/', $part) === 1) {
                $part = $areaCode.$part;
            }

            if (preg_match('/^(0\d{1,3})\//', $part, $m) === 1) {
                $areaCode = $m[1];
            }

            $normalized = $this->normalizePhone($part);

            if ($normalized === null) {
                return null;
            }

            $numbers[] = $normalized;
        }

        $numbers = array_values(array_unique($numbers));

        return $numbers === [] ? null : $numbers;
    }

    /** Kópia CustomerDataRules::normalizePhone — migrácia nesmie závisieť od kódu, ktorý sa časom zmení. */
    private function normalizePhone(string $phone): ?string
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

            // +4210556367007 — nula za predvoľbou navyše.
            if (str_starts_with($digits, '4210') && strlen($digits) === 13) {
                $digits = '421'.substr($digits, 4);
            }

            return preg_match('/^\d{9,15}$/', $digits) === 1 ? '+'.$digits : null;
        }

        if (preg_match('/^\d+$/', $value) !== 1) {
            return null;
        }

        if (str_starts_with($value, '0') && strlen($value) === 10) {
            return '+421'.substr($value, 1);
        }

        if (strlen($value) === 9) {
            return '+421'.$value;
        }

        if (str_starts_with($value, '421') && strlen($value) === 12) {
            return '+'.$value;
        }

        return null;
    }
};
