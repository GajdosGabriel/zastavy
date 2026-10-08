<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// Historické objednávky z priameho e-mailu (orders:import-gmail) prišli bez expedície,
// takže ich OrderFilter::isActive() dodnes ukazoval medzi aktívnymi. Každá dostane jeden
// dodací list na celé nevystornované množstvo, vystavený aj odoslaný v deň prijatia
// objednávky. Idempotentné — objednávka, ktorá už dodací list alebo výdaj má, sa preskočí.
//
// Zapisuje sa cez query builder, teda bez modelových eventov, e-mailov a spúšťačov
// emailingu. Položky sú vlastné (bez variantu), preto inventory_delta = 0 a stav skladu
// sa nemení. Status objednávky ostáva „archived".
return new class extends Migration
{
    public function up(): void
    {
        DB::transaction(function () {
            $this->orders()
                ->whereNotExists(fn ($query) => $query->selectRaw('1')->from('shippings')->whereColumn('shippings.order_id', 'orders.id'))
                ->whereNotExists(fn ($query) => $query->selectRaw('1')->from('stocks')->whereColumn('stocks.order_id', 'orders.id'))
                ->orderBy('id')
                ->get(['id', 'created_at'])
                ->each(function ($order) {
                    $items = DB::table('order_products')
                        ->where('order_id', $order->id)
                        ->whereNull('deleted_at')
                        ->orderBy('id')
                        ->get(['id', 'quantity', 'storno'])
                        ->map(fn ($item) => ['id' => $item->id, 'quantity' => (int) $item->quantity - (int) $item->storno])
                        ->filter(fn ($item) => $item['quantity'] > 0);

                    // Neúplná objednávka bez položiek aktívna nie je a nemá čo expedovať.
                    if ($items->isEmpty()) {
                        return;
                    }

                    $at = $order->created_at;

                    $shippingId = DB::table('shippings')->insertGetId([
                        'status' => 'active',
                        'order_id' => $order->id,
                        'dispatched_at' => $at,
                        'created_at' => $at,
                        'updated_at' => $at,
                    ]);

                    DB::table('stocks')->insert($items->map(fn ($item) => [
                        'status' => 'active',
                        'order_id' => $order->id,
                        'shipping_id' => $shippingId,
                        'order_product_id' => $item['id'],
                        'quantity' => $item['quantity'],
                        'inventory_delta' => 0,
                        'created_at' => $at,
                        'updated_at' => $at,
                    ])->values()->all());

                    // „Odoslanie bez e-mailu" — inak by dodacie listy vyskočili vo filtri Nenotifikované.
                    DB::table('notices')->insert([
                        'status' => 'active',
                        'fileable_id' => $shippingId,
                        'fileable_type' => \App\Models\Shipping::class,
                        'notice' => 'none',
                        'created_at' => $at,
                        'updated_at' => $at,
                    ]);
                });
        });
    }

    public function down(): void
    {
        // Archivovanú objednávku aplikácia expedovať nedovolí, takže dodací list
        // datovaný presne vznikom objednávky môže pochádzať len odtiaľto.
        DB::transaction(function () {
            $ids = DB::table('shippings')
                ->join('orders', 'orders.id', '=', 'shippings.order_id')
                ->where('orders.serial_number', 'like', 'GMAIL-%')
                ->where('orders.status', 'archived')
                ->where('orders.snapshot_source', 'reconstructed')
                ->whereColumn('shippings.created_at', 'orders.created_at')
                ->whereColumn('shippings.dispatched_at', 'orders.created_at')
                ->pluck('shippings.id');

            foreach ($ids->chunk(500) as $chunk) {
                DB::table('notices')->where('fileable_type', \App\Models\Shipping::class)->whereIn('fileable_id', $chunk)->delete();
                DB::table('stocks')->whereIn('shipping_id', $chunk)->delete();
                DB::table('shippings')->whereIn('id', $chunk)->delete();
            }
        });
    }

    private function orders()
    {
        return DB::table('orders')
            ->where('serial_number', 'like', 'GMAIL-%')
            ->where('status', 'archived')
            ->where('snapshot_source', 'reconstructed');
    }
};
