<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ImportLegacyDb extends Command
{
    protected $signature   = 'db:import-legacy {--legacy-db=zastavy_legacy} {--force}';
    protected $description = 'Import a transformácia dát zo starej DB do aktuálnej';

    // Mapa customer_id → user_id, plnená po importUsers
    private array $customerUserMap = [];

    public function handle(): int
    {
        $legacyDb = $this->option('legacy-db');

        $main = config('database.connections.' . config('database.default'));
        Config::set('database.connections.legacy', array_merge($main, ['database' => $legacyDb]));
        DB::purge('legacy');

        try {
            DB::connection('legacy')->statement('SELECT 1');
        } catch (\Exception $e) {
            $this->error("Legacy DB '$legacyDb' nie je dostupná: " . $e->getMessage());
            return Command::FAILURE;
        }

        if (! $this->option('force') && ! $this->confirm("Toto VYMAŽE všetky dáta v aktuálnej DB. Pokračovať?")) {
            return Command::SUCCESS;
        }

        $this->info('Spúšťam import...');
        DB::statement('SET FOREIGN_KEY_CHECKS=0');

        $this->truncateTables();
        $this->importCustomers();
        $this->importAdminUsers();
        $this->importUsersFromCustomers();
        $this->importCategories();
        $this->importProducts();
        $this->importImages();
        $this->importCategoryProduct();
        $this->importOrders();
        $this->importOrderProducts();
        $this->importShippings();
        $this->importStocks();
        $this->createMissingStocks();
        $this->importNotices();
        $this->createMissingShippingNotices();
        $this->importMarks();
        $this->updateOrderStatuses();

        DB::statement('SET FOREIGN_KEY_CHECKS=1');
        $this->info('✓ Import dokončený.');
        return Command::SUCCESS;
    }

    // -------------------------------------------------------------------------

    private function truncateTables(): void
    {
        $this->line('  Mazanie existujúcich dát...');
        foreach ([
            'order_return_items', 'order_returns',
            'stocks', 'notices', 'marks',
            'order_products', 'shippings',
            'orders', 'images',
            'category_product', 'products', 'categories',
            'users', 'customers',
        ] as $table) {
            DB::table($table)->truncate();
        }
    }

    private function importCustomers(): void
    {
        $this->line('  Customers...');
        $rows = DB::connection('legacy')->table('customers')->get();

        foreach ($rows->chunk(500) as $chunk) {
            DB::table('customers')->insert(
                $chunk->map(fn($r) => [
                    'id'                => $r->id,
                    'status'            => 'active',
                    'name'              => $r->name,
                    'company'           => $r->company,
                    'slug'              => $r->slug,
                    'phone'             => $r->phone,
                    'email'             => $r->email ?? null,
                    'username'          => $r->username,
                    'street'            => $r->street,
                    'postcode'          => $r->postcode,
                    'city'              => $r->city,
                    'ico'               => $r->ico,
                    'dic'               => $r->dic,
                    'ic_dic'            => $r->ic_dic,
                    'note'              => $r->note,
                    'last_login'        => $r->last_login,
                    'remember_token'    => $r->remember_token,
                    'email_verified_at' => $r->email_verified_at,
                    'created_at'        => $r->created_at,
                    'updated_at'        => $r->updated_at,
                    'deleted_at'        => $r->deleted_at,
                ])->toArray()
            );
        }
        $this->line("    → {$rows->count()} zákazníkov");
    }

    private function importAdminUsers(): void
    {
        $this->line('  Admin users...');
        $users = DB::connection('legacy')->table('users')->get();

        foreach ($users as $u) {
            $parts = preg_split('/\s+/', trim((string) $u->name), 2);
            DB::table('users')->insert([
                'id'                => $u->id,
                'uuid'              => (string) Str::uuid(),
                'status'            => 'active',
                'name'              => $u->name,
                'firstName'         => $parts[0] ?? 'Admin',
                'lastName'          => $parts[1] ?? '',
                'slug'              => $u->slug ?? Str::slug($u->name ?: 'admin'),
                'username'          => $u->name,
                'email'             => $u->email,
                'phone'             => null,
                'customer_id'       => $u->customer_id ?? null,
                'email_verified_at' => $u->email_verified_at,
                'password'          => $u->password,
                'remember_token'    => $u->remember_token,
                'created_at'        => $u->created_at,
                'updated_at'        => $u->updated_at,
                'deleted_at'        => $u->deleted_at ?? null,
            ]);

            if ($u->customer_id ?? null) {
                $this->customerUserMap[$u->customer_id] = $u->id;
            }
        }
        $this->line("    → {$users->count()} admin user(ov)");
    }

    private function importUsersFromCustomers(): void
    {
        $this->line('  Users z customers...');

        // Najstarší dátum objednávky pre každého zákazníka
        $oldestOrderDate = DB::connection('legacy')->table('orders')
            ->select('customer_id', DB::raw('MIN(created_at) as first_order_at'))
            ->groupBy('customer_id')
            ->pluck('first_order_at', 'customer_id');

        $existingEmails = DB::table('users')->pluck('email')->flip();
        $passwordHash   = bcrypt(Str::random(32));
        $count = 0;

        DB::connection('legacy')->table('customers')
            ->whereNotNull('email')
            ->orderBy('id')
            ->chunk(500, function ($customers) use (&$existingEmails, $passwordHash, &$count, $oldestOrderDate) {
                foreach ($customers as $c) {
                    // Admin user s rovnakým emailom — priradiť customer_id a pokračuj
                    if (isset($existingEmails[$c->email])) {
                        $uid = DB::table('users')->where('email', $c->email)->value('id');
                        if ($uid) {
                            DB::table('users')->where('id', $uid)->update(['customer_id' => $c->id]);
                            $this->customerUserMap[$c->id] = $uid;
                        }
                        continue;
                    }

                    $username  = $c->username ?: $c->name;
                    $parts     = preg_split('/\s+/', trim((string) $username), 2);
                    $firstName = $parts[0] ?: ((string) ($c->company ?: 'Kontakt'));
                    $lastName  = $parts[1] ?? '';
                    $slug      = Str::slug($username ?: $c->company ?: 'kontakt-' . $c->id);
                    $base = $slug; $i = 1;
                    while (DB::table('users')->where('slug', $slug)->exists()) {
                        $slug = $base . '-' . $i++;
                    }

                    // created_at = dátum najstaršej objednávky, alebo created_at zákazníka
                    $createdAt = $oldestOrderDate[$c->id] ?? $c->created_at;

                    $id = DB::table('users')->insertGetId([
                        'uuid'              => (string) Str::uuid(),
                        'status'            => 'active',
                        'name'              => $username ?: $c->company ?: 'Kontakt',
                        'firstName'         => $firstName,
                        'lastName'          => $lastName,
                        'slug'              => $slug,
                        'username'          => $username,
                        'email'             => $c->email,
                        'phone'             => $c->phone,
                        'customer_id'       => $c->id,
                        'email_verified_at' => $c->email_verified_at,
                        'password'          => $passwordHash,
                        'remember_token'    => null,
                        'created_at'        => $createdAt,
                        'updated_at'        => $c->updated_at ?? $createdAt,
                        'deleted_at'        => $c->deleted_at,
                    ]);

                    $this->customerUserMap[$c->id] = $id;
                    $existingEmails[$c->email]     = true;
                    $count++;
                }
            });

        $this->line("    → $count nových userov z customers");
    }

    private function importCategories(): void
    {
        $this->line('  Categories...');
        $rows = DB::connection('legacy')->table('categories')->get();
        if ($rows->isEmpty()) { $this->line('    → 0'); return; }

        DB::table('categories')->insert(
            $rows->map(fn($r) => [
                'id'         => $r->id,
                'status'     => 'active',
                'name'       => $r->name,
                'slug'       => $r->slug,
                'created_at' => $r->created_at,
                'updated_at' => $r->updated_at,
                'deleted_at' => $r->deleted_at ?? null,
            ])->toArray()
        );
        $this->line("    → {$rows->count()} kategórií");
    }

    private function importProducts(): void
    {
        $this->line('  Products...');
        $rows = DB::connection('legacy')->table('products')->get();
        if ($rows->isEmpty()) { $this->line('    → 0'); return; }

        DB::table('products')->insert(
            $rows->map(fn($r) => [
                'id'          => $r->id,
                'code'        => sprintf('TOV-%06d', $r->id),
                'status'      => ($r->published ?? true) ? 'active' : 'hidden',
                'name'        => $r->name,
                'slug'        => $r->slug,
                'description' => $r->description,
                'quantity'    => $r->quantity,
                'weight'      => $r->weight,
                'price'       => $r->price,
                'sale_price'  => $r->sale_price,
                'discount'    => $r->discount,
                'vat'         => $r->vat,
                'image_id'    => $r->image_id,
                'featured'    => $r->featured ?? false,
                'published'   => $r->published ?? true,
                'attributes'  => $r->attributes,
                'unit_value'  => $r->unit_value ?? 'ks',
                'min_order'   => $r->min_order ?? 1,
                'created_at'  => $r->created_at,
                'updated_at'  => $r->updated_at,
                'deleted_at'  => $r->deleted_at ?? null,
            ])->toArray()
        );
        $this->line("    → {$rows->count()} produktov");
    }

    private function importImages(): void
    {
        $this->line('  Images...');
        $rows = DB::connection('legacy')->table('images')->get();
        if ($rows->isEmpty()) { $this->line('    → 0'); return; }

        DB::table('images')->insert(
            $rows->map(fn($r) => [
                'id'            => $r->id,
                'sort_order'    => $r->sort_order ?? 0,
                'status'        => 'active',
                'fileable_id'   => $r->fileable_id ?? null,
                'fileable_type' => $r->fileable_type ?? null,
                'name'          => $r->name,
                'path'          => $r->path ?? null,
                'org_name'      => $r->org_name ?? null,
                'mime'          => $r->mime ?? null,
                // Starý DB mal size/mime ako varchar s hodnotou 'jpg' — konverzia na int alebo null
                'size'          => is_numeric($r->size ?? null) ? (int) $r->size : null,
                'heigh'         => is_numeric($r->heigh ?? null) ? (int) $r->heigh : ($r->heigh ?? null),
                'with'          => is_numeric($r->with ?? null) ? (int) $r->with : ($r->with ?? null),
                'created_at'    => $r->created_at,
                'updated_at'    => $r->updated_at,
                'deleted_at'    => $r->deleted_at ?? null,
            ])->toArray()
        );
        $this->line("    → {$rows->count()} obrázkov");
    }

    private function importCategoryProduct(): void
    {
        $this->line('  Category-product väzby...');
        $rows = DB::connection('legacy')->table('category_product')->get();
        if ($rows->isNotEmpty()) {
            DB::table('category_product')->insert(
                $rows->map(fn($r) => [
                    'category_id' => $r->category_id,
                    'product_id'  => $r->product_id,
                ])->toArray()
            );
        }
        $this->line("    → {$rows->count()} väzieb");
    }

    private function importOrders(): void
    {
        $this->line('  Orders...');

        // Zákazníci: name/email/phone pre snapshot v objednávke
        $customers = DB::connection('legacy')->table('customers')
            ->get(['id', 'name', 'company', 'email', 'phone'])
            ->keyBy('id');

        $count = 0;
        DB::connection('legacy')->table('orders')
            ->orderBy('id')
            ->chunk(500, function ($orders) use ($customers, &$count) {
                DB::table('orders')->insert(
                    collect($orders)->map(function ($o) use ($customers) {
                        $c = $customers[$o->customer_id] ?? null;
                        return [
                            'id'            => $o->id,
                            'uuid'          => (string) Str::uuid(),
                            'status'        => $this->deriveOrderStatus($o),
                            'customer_id'   => $o->customer_id,
                            'user_id'       => $this->customerUserMap[$o->customer_id] ?? null,
                            // Snapshot kontaktných údajov zákazníka v čase objednávky
                            'name'          => $c ? ($c->company ?: $c->name) : null,
                            'email'         => $c->email ?? null,
                            'phone'         => $c->phone ?? null,
                            'note'          => null,
                            'wants_coupon'  => false,
                            'serial_number' => $o->serial_number,
                            'isOpened'      => $o->isOpened,
                            'isDelivered'   => $o->isDelivered,
                            'shipping_method_id' => null,
                            'shipping_price'     => null,
                            'payment_method_id'  => null,
                            'payment_fee'        => null,
                            'coupon_id'          => null,
                            'discount_amount'    => null,
                            'created_at'    => $o->created_at,
                            'updated_at'    => $o->updated_at,
                            'deleted_at'    => $o->deleted_at,
                        ];
                    })->toArray()
                );
                $count += count($orders);
            });

        $this->line("    → $count objednávok");
    }

    private function importOrderProducts(): void
    {
        $this->line('  Order products...');
        $count = 0;

        DB::connection('legacy')->table('order_products')
            ->orderBy('id')
            ->chunk(1000, function ($rows) use (&$count) {
                DB::table('order_products')->insert(
                    collect($rows)->map(fn($r) => [
                        'id'         => $r->id,
                        'status'     => $r->deleted_at ? 'cancelled' : 'active',
                        'order_id'   => $r->order_id,
                        'product_id' => $r->product_id,
                        'quantity'   => $r->quantity,
                        'storno'     => $r->storno,
                        'sent_at'    => $r->sent_at,
                        'price'      => $r->price,
                        'total'      => $r->total,
                        'created_at' => $r->created_at,
                        'updated_at' => $r->updated_at,
                        'deleted_at' => $r->deleted_at,
                    ])->toArray()
                );
                $count += count($rows);
            });

        $this->line("    → $count položiek objednávok");
    }

    private function importShippings(): void
    {
        $this->line('  Shippings (existujúce)...');
        $count = 0;

        DB::connection('legacy')->table('shippings')
            ->orderBy('id')
            ->chunk(1000, function ($rows) use (&$count) {
                DB::table('shippings')->insert(
                    collect($rows)->map(fn($r) => [
                        'id'         => $r->id,
                        'status'     => 'active',
                        'order_id'   => $r->order_id,
                        'created_at' => $r->created_at,
                        'updated_at' => $r->updated_at,
                    ])->toArray()
                );
                $count += count($rows);
            });

        $this->line("    → $count expedícií");
    }

    private function importStocks(): void
    {
        $this->line('  Stocks (existujúce)...');
        $count = 0;

        DB::connection('legacy')->table('stocks')
            ->orderBy('id')
            ->chunk(1000, function ($rows) use (&$count) {
                DB::table('stocks')->insert(
                    collect($rows)->map(fn($r) => [
                        'id'               => $r->id,
                        'status'           => 'active',
                        'order_id'         => $r->order_id,
                        'shipping_id'      => $r->shipping_id,
                        'order_return_id'  => null,
                        'order_product_id' => $r->order_product_id,
                        'quantity'         => $r->quantity,
                        'created_at'       => $r->created_at,
                        'updated_at'       => $r->updated_at,
                        'deleted_at'       => $r->deleted_at ?? null,
                    ])->toArray()
                );
                $count += count($rows);
            });

        $this->line("    → $count stock záznamov");
    }

    /**
     * Objednávky bez akýchkoľvek pohybov na sklade dostanú jeden shipping
     * a stock záznamy s dátumom objednávky (tak ako keby expedícia prebehla v deň objednávky).
     */
    private function createMissingStocks(): void
    {
        $this->line('  Vytváranie chýbajúcich expedícií/stocks...');

        // order_ids ktoré už majú nejaký stock záznam
        $ordersWithStocks = DB::connection('legacy')->table('stocks')
            ->distinct()
            ->pluck('order_id')
            ->flip();

        // order_products pre objednávky bez stocks, zoskupené po order_id
        $orderProducts = DB::connection('legacy')->table('order_products')
            ->whereNotIn('order_id', $ordersWithStocks->keys()->toArray() ?: [0])
            ->whereNull('deleted_at')
            ->get(['id', 'order_id', 'quantity', 'storno', 'sent_at', 'created_at']);

        if ($orderProducts->isEmpty()) {
            $this->line('    → žiadne chýbajúce expedície');
            return;
        }

        // Dátumy objednávok
        $orderDates = DB::connection('legacy')->table('orders')
            ->pluck('created_at', 'id');

        // Najvyššie existujúce id v shippings (aby sme nevytvorili kolíziu)
        $maxShippingId = DB::table('shippings')->max('id') ?? 0;
        $maxStockId    = DB::table('stocks')->max('id') ?? 0;

        $grouped = $orderProducts->groupBy('order_id');
        $shippingRows = [];
        $stockRows    = [];

        foreach ($grouped as $orderId => $items) {
            $date = $orderDates[$orderId] ?? now()->toDateTimeString();
            $maxShippingId++;

            $shippingRows[] = [
                'id'         => $maxShippingId,
                'status'     => 'active',
                'order_id'   => $orderId,
                'created_at' => $date,
                'updated_at' => $date,
            ];

            foreach ($items as $item) {
                $qty = max(0, $item->quantity - ($item->storno ?? 0));
                if ($qty <= 0) continue;

                $maxStockId++;
                $stockRows[] = [
                    'id'               => $maxStockId,
                    'status'           => 'active',
                    'order_id'         => $orderId,
                    'shipping_id'      => $maxShippingId,
                    'order_return_id'  => null,
                    'order_product_id' => $item->id,
                    'quantity'         => $qty,
                    'created_at'       => $date,
                    'updated_at'       => $date,
                    'deleted_at'       => null,
                ];
            }
        }

        // Batch insert
        foreach (array_chunk($shippingRows, 500) as $batch) {
            DB::table('shippings')->insert($batch);
        }
        foreach (array_chunk($stockRows, 500) as $batch) {
            DB::table('stocks')->insert($batch);
        }

        $this->line("    → {$grouped->count()} nových expedícií, " . count($stockRows) . " stock záznamov");
    }

    private function importNotices(): void
    {
        $this->line('  Notices...');
        $count = 0;

        DB::connection('legacy')->table('notices')
            ->orderBy('id')
            ->chunk(1000, function ($rows) use (&$count) {
                DB::table('notices')->insert(
                    collect($rows)->map(fn($r) => [
                        'id'            => $r->id,
                        'status'        => 'active',
                        'fileable_id'   => $r->fileable_id,
                        'fileable_type' => $r->fileable_type,
                        'notice'        => $r->notice,
                        'created_at'    => $r->created_at,
                        'updated_at'    => $r->updated_at,
                        'deleted_at'    => $r->deleted_at ?? null,
                    ])->toArray()
                );
                $count += count($rows);
            });

        $this->line("    → $count oznámení");
    }

    private function createMissingShippingNotices(): void
    {
        $this->line('  Chýbajúce oznámenia o expedícii...');

        $rows = DB::table('shippings as s')
            ->leftJoin('notices as n', function ($j) {
                $j->on('n.fileable_id', '=', 's.id')
                  ->where('n.fileable_type', '=', 'App\Models\Shipping');
            })
            ->whereNull('n.id')
            ->select('s.id', 's.created_at')
            ->get();

        if ($rows->isEmpty()) {
            $this->line('    → žiadne chýbajúce');
            return;
        }

        foreach ($rows->chunk(500) as $chunk) {
            DB::table('notices')->insert(
                $chunk->map(fn($s) => [
                    'status'        => 'active',
                    'fileable_id'   => $s->id,
                    'fileable_type' => 'App\Models\Shipping',
                    'notice'        => 'email',
                    'created_at'    => $s->created_at,
                    'updated_at'    => $s->created_at,
                    'deleted_at'    => null,
                ])->toArray()
            );
        }

        $this->line("    → {$rows->count()} nových oznámení");
    }

    private function importMarks(): void
    {
        $this->line('  Marks...');
        $rows = DB::connection('legacy')->table('marks')->get();
        if ($rows->isEmpty()) { $this->line('    → 0'); return; }

        DB::table('marks')->insert(
            $rows->map(fn($r) => [
                'id'            => $r->id,
                'status'        => 'active',
                'fileable_id'   => $r->fileable_id,
                'fileable_type' => $r->fileable_type,
                'user_id'       => $r->user_id,
                'created_at'    => $r->created_at,
                'updated_at'    => $r->updated_at,
            ])->toArray()
        );
        $this->line("    → {$rows->count()} značiek");
    }

    private function updateOrderStatuses(): void
    {
        $this->line('  Aktualizujem statusy objednávok...');

        // Čiastočne expedované
        DB::statement("
            UPDATE orders o
            INNER JOIN (
                SELECT op.order_id,
                       SUM(op.quantity - COALESCE(op.storno,0)) AS total_qty,
                       COALESCE(SUM(s.quantity), 0)             AS shipped_qty
                FROM order_products op
                LEFT JOIN stocks s ON s.order_product_id = op.id AND s.deleted_at IS NULL
                WHERE op.deleted_at IS NULL
                GROUP BY op.order_id
                HAVING shipped_qty > 0 AND shipped_qty < total_qty
            ) calc ON calc.order_id = o.id
            SET o.status = 'partially_shipped'
            WHERE o.status NOT IN ('cancelled','shipped')
        ");

        // Plne expedované
        DB::statement("
            UPDATE orders o
            INNER JOIN (
                SELECT op.order_id,
                       SUM(op.quantity - COALESCE(op.storno,0)) AS total_qty,
                       COALESCE(SUM(s.quantity), 0)             AS shipped_qty
                FROM order_products op
                LEFT JOIN stocks s ON s.order_product_id = op.id AND s.deleted_at IS NULL
                WHERE op.deleted_at IS NULL
                GROUP BY op.order_id
                HAVING total_qty > 0 AND shipped_qty >= total_qty
            ) calc ON calc.order_id = o.id
            SET o.status = 'shipped'
            WHERE o.status NOT IN ('cancelled')
        ");

        $this->line('    → statusy aktualizované');
    }

    private function deriveOrderStatus(object $order): string
    {
        if ($order->deleted_at !== null) return 'cancelled';
        if ($order->isDelivered)        return 'shipped';
        if ($order->isOpened)           return 'processing';
        return 'draft';
    }
}
