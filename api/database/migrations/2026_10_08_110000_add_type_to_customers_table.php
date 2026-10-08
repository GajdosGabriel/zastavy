<?php

use App\Services\Customers\CustomerTypeClassifier;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// Druh zákazníka: obec, škola, firma, súkromná osoba (App\Enums\CustomerType).
// Doterajším riadkom ho pridelí CustomerTypeClassifier — ten istý, ktorý ho odteraz
// prideľuje novým zákazníkom. Kde pravidlá nestačia (preklep v názve, „Nezistená
// organizácia — e-mail", obec bez slova „obec"), rozhodla ručná kontrola celej tabuľky;
// tieto výnimky sú v MANUAL. Kľúčom je odtlačok názvu, nie id — id sa medzi databázami
// môže líšiť (import z Gmailu) a názov v repozitári nemá čo robiť. Riadok, ktorého názov
// sa medzičasom zmenil, dostane typ podľa pravidiel.
// Idempotentné — vyplnený typ sa neprepíše.
return new class extends Migration
{
    private const MANUAL = [
        'school' => [
            '6b7e7dedfe09', 'e9b63b1d8ae6', '56bf5d734ade', '5c4a8a110ea4', '3bf1cdb3b149',
            '093ecca94d1e', 'e552e8b6921e', '2ab8b17c66bd', 'df20b76f94ce', 'c91f57c3dc42',
            '136eb9cebd44', '2d1073abc0b6', '64397af65ede', '89e66a988a84', '393a831b1723',
        ],
        'municipality' => [
            '4d3cab0796d0', 'acd2cb844017', '843a486bb5a8', '4a5601a2816a', '5b69e339f239',
            '28b03947f8d7', '468042916624', '9f85b6f33911', '8d814b7b2324', 'e56601700526',
            '0fd4e560d1d2', '5467361bfc4a', '71700c0dede7', 'dbea31c31733', 'e8ebb26ffe4f',
            '2f309a97b7c8', '0db5cd4bf84c', '2e5b1b9e0d8e', '704d26e2599d', 'fa5ff317aa68',
            '1410dfdcfd60', 'f7db0b47881d', '42e822287077', 'c39f76a308b2', '2ad05787d0e6',
            '3986991ed15a', 'a7298169b5c1', '3d00346119a6', '6384c1e1354b',
        ],
        'person' => [
            '11f64f5f9e7b', 'e5c859e89968',
        ],
    ];

    public function up(): void
    {
        if (! Schema::hasColumn('customers', 'type')) {
            Schema::table('customers', function (Blueprint $table) {
                $table->string('type', 20)->nullable()->after('status')->index();
            });
        }

        $manual = [];
        foreach (self::MANUAL as $type => $hashes) {
            $manual += array_fill_keys($hashes, $type);
        }

        $classifier = new CustomerTypeClassifier();

        // Aj zmazaní — po obnovení zákazníka má typ sedieť rovnako ako u ostatných.
        DB::table('customers')->whereNull('type')->orderBy('id')->chunkById(500, function ($rows) use ($manual, $classifier) {
            $contacts = DB::table('users')
                ->whereIn('customer_id', $rows->pluck('id'))
                ->orderByDesc('id')
                ->pluck('username', 'customer_id');

            $byType = [];

            foreach ($rows as $row) {
                $type = $manual[substr(sha1(trim((string) $row->company)), 0, 12)]
                    ?? $classifier->classify($row->company, $row->ico, $contacts[$row->id] ?? null)->value;

                $byType[$type][] = $row->id;
            }

            foreach ($byType as $type => $ids) {
                DB::table('customers')->whereIn('id', $ids)->update(['type' => $type]);
            }
        });
    }

    public function down(): void
    {
        if (Schema::hasColumn('customers', 'type')) {
            Schema::table('customers', function (Blueprint $table) {
                $table->dropIndex(['type']);
                $table->dropColumn('type');
            });
        }
    }
};
