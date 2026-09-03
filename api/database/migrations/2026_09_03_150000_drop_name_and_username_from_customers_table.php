<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Meno kontaktnej osoby patrí do `users`, nie do `customers`.
 *
 * `customers.name` znamenal dve rôzne veci naraz: staré riadky v ňom mali meno
 * človeka, novšie názov firmy — checkout doň zapisoval `company ?: name`.
 * Súbežne tá istá hodnota žila v `users.username` a formulár aj API ukazovali
 * to druhé. Kontrola údajov preto vedela mať výhradu k obom stranám a oprava
 * jednej vyzerala, že nespravila nič.
 *
 * `customers.username` je z tej istej rodiny: zapisoval sa pri každom uložení
 * zákazníka a nečítal ho nikto.
 *
 * Po tejto migrácii má meno kontaktnej osoby jediné miesto — `users.username`.
 * `Customer::getNameAttribute()` ho odtiaľ podáva ďalej, takže `$customer->name`
 * číta rovnako ako predtým.
 */
return new class extends Migration
{
    public function up(): void
    {
        $this->backup();
        $this->backfillContacts();

        Schema::table('customers', function (Blueprint $table) {
            $table->dropColumn(['name', 'username']);
        });
    }

    /**
     * Zákazníci zo starého importu nemajú kontaktnú osobu v `users` — meno majú
     * len v stĺpci, ktorý o chvíľu zmizne. Bez tohto kroku by sa stratilo.
     */
    private function backfillContacts(): void
    {
        $now = now();

        DB::table('customers')
            ->whereNotExists(function ($query) {
                $query->select(DB::raw(1))
                    ->from('users')
                    ->whereColumn('users.customer_id', 'customers.id');
            })
            // chunkById, nie chunk: vložením používateľa prestane zákazník
            // spĺňať podmienku vyššie a stránkovanie cez OFFSET by tým pádom
            // preskakovalo celé bloky riadkov.
            ->chunkById(200, function ($customers) use ($now) {
                $rows = [];

                foreach ($customers as $customer) {
                    $name = trim((string) ($customer->username ?: $customer->name));
                    $email = trim((string) $customer->email);

                    // Bez e-mailu sa používateľ založiť nedá (stĺpec je NOT NULL
                    // a e-mail je preň identifikátor v rámci firmy). V dátach
                    // taký zákazník nie je, ale migrácia sa nesmie rozsypať,
                    // keby pribudol.
                    if ($name === '' || $email === '') {
                        continue;
                    }

                    $parts = preg_split('/\s+/u', $name, 2) ?: [];

                    $rows[] = [
                        'uuid' => (string) Str::uuid(),
                        'customer_id' => $customer->id,
                        'status' => 'active',
                        'name' => $name,
                        'firstName' => $parts[0] ?: 'Kontakt',
                        'lastName' => $parts[1] ?? '',
                        'username' => $name,
                        'slug' => Str::slug($name) ?: 'kontakt-' . $customer->id,
                        'email' => $email,
                        'phone' => $customer->phone,
                        // Prihlasovanie cez tieto účty nikto nečaká; heslo je
                        // náhodné práve preto, aby sa cez ne prihlásiť nedalo.
                        'password' => Hash::make(Str::random(32)),
                        'created_at' => $customer->created_at ?: $now,
                        'updated_at' => $now,
                    ];
                }

                if ($rows !== []) {
                    DB::table('users')->insert($rows);
                }
            });
    }

    /**
     * Odloží obe hodnoty do CSV skôr, než zmiznú.
     *
     * Väčšina je duplicita `users.username`, ale asi v stovke riadkov drží
     * `name` predchádzajúcu kontaktnú osobu, ktorú prepísala novšia objednávka
     * z tej istej adresy. Objednávkam to nevadí — `orders.name` je snapshot
     * spravený pri objednávke — ale zmazať to potichu sa nepatrí.
     */
    private function backup(): void
    {
        // Prázdna tabuľka nemá čo zálohovať. Bez tejto podmienky by každý beh
        // testov (migrácia na čistej databáze) nechal v priečinku súbor,
        // v ktorom je len hlavička.
        if (! DB::table('customers')->exists()) {
            return;
        }

        $directory = storage_path('app/backups');

        if (! is_dir($directory)) {
            mkdir($directory, 0775, true);
        }

        $path = $directory . '/customers-name-' . now()->format('Ymd-His') . '.csv';
        $handle = fopen($path, 'w');

        if ($handle === false) {
            return;
        }

        fwrite($handle, "\xEF\xBB\xBF");
        fputcsv($handle, ['customer_id', 'name', 'username', 'company', 'contact_username'], ';');

        DB::table('customers')
            ->leftJoin('users', 'users.customer_id', '=', 'customers.id')
            ->orderBy('customers.id')
            ->select([
                'customers.id',
                'customers.name',
                'customers.username',
                'customers.company',
                'users.username as contact_username',
            ])
            ->chunk(500, function ($rows) use ($handle) {
                foreach ($rows as $row) {
                    fputcsv($handle, [
                        $row->id,
                        $row->name,
                        $row->username,
                        $row->company,
                        $row->contact_username,
                    ], ';');
                }
            });

        fclose($handle);
    }

    /**
     * Stĺpce sa vrátia a naplnia z kontaktnej osoby.
     *
     * Nie je to úplná obnova: riadky, kde sa `name` a `users.username` rozišli,
     * dostanú späť hodnotu z `users`. Pôvodné znenie zostalo v CSV zo `backup()`.
     */
    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->string('name', 150)->nullable()->after('status');
            $table->string('username', 100)->nullable()->after('email');
        });

        DB::table('customers')->orderBy('id')->chunk(200, function ($customers) {
            foreach ($customers as $customer) {
                $name = DB::table('users')
                    ->where('customer_id', $customer->id)
                    ->orderBy('id')
                    ->value('username');

                DB::table('customers')->where('id', $customer->id)->update([
                    'name' => $name ?: $customer->company,
                    'username' => $name,
                ]);
            }
        });

        DB::statement('ALTER TABLE customers MODIFY name VARCHAR(150) NOT NULL');
    }
};
