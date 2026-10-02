<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// Jednorazové opravy dát zachytené pri kontrole: prázdna kategória, preklep v e-maile,
// zduplikovaný popis produktu a telefóny v rôznych tvaroch. Idempotentné — druhé spustenie
// už nenájde čo meniť. Objednávky sa nemenia: telefón v nich je historický snapshot.
return new class extends Migration
{
    public function up(): void
    {
        $this->removeBlankCategories();
        $this->fixEmailTypos();
        $this->dedupeProductDescriptions();
        $this->normalizePhones();
    }

    public function down(): void
    {
        // Opravy dát sa nevracajú.
    }

    /** Kategória bez názvu nemá v ponuke čo hľadať — zmizne z produktov aj z formulára. */
    private function removeBlankCategories(): void
    {
        $ids = DB::table('categories')
            ->whereNull('deleted_at')
            ->whereRaw("TRIM(name) = ''")
            ->pluck('id');

        if ($ids->isEmpty()) {
            return;
        }

        DB::table('category_product')->whereIn('category_id', $ids)->delete();
        DB::table('categories')->whereIn('id', $ids)->update(['deleted_at' => now(), 'updated_at' => now()]);
    }

    /** gmai.com → gmail.com (rovnaký preklep v users aj customers). */
    private function fixEmailTypos(): void
    {
        foreach (['users', 'customers'] as $table) {
            if (! Schema::hasColumn($table, 'email')) {
                continue;
            }

            DB::table($table)
                ->where('email', 'like', '%@gmai.com')
                ->update(['email' => DB::raw("CONCAT(SUBSTRING(email, 1, CHAR_LENGTH(email) - CHAR_LENGTH('@gmai.com')), '@gmail.com')")]);
        }
    }

    /**
     * Popis „Štátny znak SR na stenu" bol po importe vložený trikrát za sebou
     * (a uvádzal rozmer 35×25 cm, ktorý nesedí s variantom). Ostane jeden odsek
     * bez rozmeru — ten je v názve variantu.
     */
    private function dedupeProductDescriptions(): void
    {
        $marker = 'Štátny znak SR je zarámovaný';
        $end = 'o štátnych symboloch.';

        $products = DB::table('products')
            ->where('name', 'Štátny znak SR na stenu')
            ->where('description', 'like', '%'.$marker.'%'.$marker.'%')
            ->get(['id', 'description']);

        foreach ($products as $product) {
            $start = mb_strpos($product->description, $marker);
            $stop = mb_strpos($product->description, $end, $start);

            if ($start === false || $stop === false) {
                continue;
            }

            $single = mb_substr($product->description, $start, $stop + mb_strlen($end) - $start);
            $single = preg_replace('/\s+/u', ' ', $single);

            DB::table('products')->where('id', $product->id)->update(['description' => $single, 'updated_at' => now()]);
        }
    }

    /** Jedno číslo → +421XXXXXXXXX. Zoznamy čísel a poznámky sa nechávajú človeku. */
    private function normalizePhones(): void
    {
        foreach (['users', 'customers', 'customer_addresses'] as $table) {
            if (! Schema::hasTable($table) || ! Schema::hasColumn($table, 'phone')) {
                continue;
            }

            DB::table($table)
                ->whereNotNull('phone')
                ->where('phone', '!=', '')
                ->orderBy('id')
                ->chunkById(500, function ($rows) use ($table) {
                    foreach ($rows as $row) {
                        $normalized = $this->normalizePhone($row->phone);

                        if ($normalized !== null && $normalized !== $row->phone) {
                            DB::table($table)->where('id', $row->id)->update(['phone' => $normalized]);
                        }
                    }
                });
        }
    }

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
