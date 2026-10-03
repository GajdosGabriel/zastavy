<?php

namespace App\Http\Controllers\Api\SuperAdmin;

use App\Filters\StockFilter;
use App\Http\Controllers\Controller;
use App\Http\Resources\StockResource;
use App\Models\ProductVariant;
use App\Models\Stock;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class StockController extends Controller
{
    // Horné hranice chránia pred preklepom (napr. kód tovaru vložený do množstva)
    // a držia cenu v rozsahu stĺpca decimal(10,2).
    private const MAX_QUANTITY = 100000;
    private const MAX_PRICE = 99999.99;
    private const MIN_RECEIVED_AT = '2000-01-01';

    private const MESSAGES = [
        'product_variant_id.required' => 'Vyberte skladovú položku.',
        'product_variant_id.exists'   => 'Vybraná skladová položka už neexistuje.',
        'quantity.required'           => 'Zadajte počet kusov.',
        'quantity.integer'            => 'Počet kusov musí byť celé číslo.',
        'quantity.not_in'             => 'Množstvo nesmie byť nula.',
        'quantity.between'            => 'Počet kusov môže byť najviac ' . self::MAX_QUANTITY . '.',
        'price.decimal'               => 'Nákupná cena môže mať najviac 2 desatinné miesta.',
        'price.prohibited'            => 'Odpis nemá nákupnú cenu.',
        'supplier.prohibited'         => 'Odpis nemá dodávateľa.',
        'note.required'               => 'Pri odpise uveďte dôvod.',
        'received_at.date_format'     => 'Dátum nemá platný formát.',
        'received_at.before_or_equal' => 'Dátum nemôže byť v budúcnosti.',
        'received_at.after_or_equal'  => 'Dátum je príliš starý.',
    ];

    private const ATTRIBUTES = [
        'product_variant_id' => 'skladová položka',
        'quantity'           => 'počet kusov',
        'price'              => 'nákupná cena',
        'note'               => 'poznámka',
        'supplier'           => 'dodávateľ',
        'document_number'    => 'číslo dokladu',
        'received_at'        => 'dátum',
    ];

    public function index(StockFilter $stockFilters)
    {
        Gate::authorize('viewAny', Stock::class);

        $stocks = Stock::with([
                'shipping.order.customer',
                'orderProduct.product',
                'orderProduct.variant',
                'productDirect',
                'variant',
                'receipt',
            ])
            ->latest()
            ->filter($stockFilters)
            ->paginate();

        return StockResource::collection($stocks);
    }

    public function summary()
    {
        Gate::authorize('viewAny', Stock::class);

        $rows = $this->summaryRows();

        return response()->json([
            'data' => $rows,
            'meta' => [
                'variants'       => $rows->count(),
                'below_zero'     => $rows->where('balance', '<', 0)->count(),
                'total_value'    => round($rows->sum('stock_value'), 2),
                'total_in'       => (int) $rows->sum('total_in'),
                'total_out'      => (int) $rows->sum('total_out'),
                'total_writeoff' => (int) $rows->sum('total_writeoff'),
            ],
        ]);
    }

    /**
     * Hlavička detailu skladovej položky — stav aj pre variant bez jediného pohybu.
     */
    public function variantSummary(int $variantId)
    {
        Gate::authorize('viewAny', Stock::class);

        $variant = ProductVariant::withTrashed()
            ->with('product:id,name,unit_value')
            ->findOrFail($variantId);

        return response()->json([
            'data' => $this->summaryRows($variantId)->first()
                ?? $this->summaryRow($variant, 0, 0, 0, null),
        ]);
    }

    /**
     * Plochý zoznam skladových položiek pre formulár príjmu. Zámerne bez
     * stránkovania — výber musí obsahovať každý variant, nielen prvú
     * stránku produktov.
     */
    public function variants()
    {
        Gate::authorize('viewAny', Stock::class);

        $map = $this->balanceMap();

        $variants = ProductVariant::query()
            ->with('product:id,name,unit_value')
            ->whereHas('product')
            ->orderBy('code')
            ->get();

        return response()->json([
            'data' => $variants->map(function (ProductVariant $variant) use ($map) {
                $in  = (int) ($map['in'][$variant->id] ?? 0);
                $out = (int) ($map['out'][$variant->id] ?? 0);
                $off = (int) ($map['writeoff'][$variant->id] ?? 0);

                return [
                    'id'               => $variant->id,
                    'product_id'       => $variant->product_id,
                    'code'             => $variant->code,
                    'name'             => $variant->product?->name,
                    'variant_name'     => $variant->name,
                    'label'            => $variant->name
                        ? $variant->product?->name . ' — ' . $variant->name
                        : $variant->product?->name,
                    'unit_value'       => $variant->product?->unit_value,
                    // Evidovaný stav na variante — to, čím sa riadi e-shop.
                    'tracked_quantity' => $variant->quantity,
                    // Stav vypočítaný z pohybov.
                    'balance'          => $in - $out - $off,
                ];
            })->values(),
        ]);
    }

    private function summaryRows(?int $variantId = null): Collection
    {
        $map = $this->balanceMap($variantId);

        $variantIds = collect($map['in']->keys())
            ->merge($map['out']->keys())
            ->merge($map['writeoff']->keys())
            ->unique();

        $variants = ProductVariant::withTrashed()
            ->whereIn('id', $variantIds)
            ->with('product:id,name,unit_value')
            ->get();

        return $variants->map(fn (ProductVariant $variant) => $this->summaryRow(
            $variant,
            (int) ($map['in'][$variant->id] ?? 0),
            (int) ($map['out'][$variant->id] ?? 0),
            (int) ($map['writeoff'][$variant->id] ?? 0),
            $map['price'][$variant->id] ?? null,
        ))
            // Nedostatkové položky patria hore — kvôli nim sa sklad otvára.
            ->sortBy('balance')
            ->values();
    }

    /**
     * Sumy pohybov po variantoch. Príjem aj odpis sa evidujú priamo na
     * variante, výdaj sa dohľadá cez položku objednávky.
     */
    private function balanceMap(?int $variantId = null): array
    {
        $receipts = DB::table('stocks')
            ->whereNull('shipping_id')
            ->whereNull('order_return_id')
            ->whereNotNull('product_variant_id')
            ->whereNull('deleted_at')
            ->when($variantId, fn ($q) => $q->where('product_variant_id', $variantId))
            ->groupBy('product_variant_id')
            ->select(
                'product_variant_id',
                DB::raw('SUM(CASE WHEN quantity > 0 THEN quantity ELSE 0 END) as total_in'),
                DB::raw('SUM(CASE WHEN quantity < 0 THEN -quantity ELSE 0 END) as total_writeoff'),
                // Vážený priemer nákupnej ceny — len z príjmov, ktoré cenu majú.
                DB::raw('SUM(CASE WHEN quantity > 0 AND price IS NOT NULL THEN quantity * price ELSE 0 END) as priced_value'),
                DB::raw('SUM(CASE WHEN quantity > 0 AND price IS NOT NULL THEN quantity ELSE 0 END) as priced_quantity'),
            )
            ->get()
            ->keyBy('product_variant_id');

        $out = DB::table('stocks')
            ->join('order_products', 'stocks.order_product_id', '=', 'order_products.id')
            ->whereNotNull('stocks.shipping_id')
            ->whereNotNull('order_products.product_variant_id')
            ->whereNull('stocks.deleted_at')
            ->when($variantId, fn ($q) => $q->where('order_products.product_variant_id', $variantId))
            ->groupBy('order_products.product_variant_id')
            ->select('order_products.product_variant_id', DB::raw('SUM(stocks.quantity) as total'))
            ->pluck('total', 'product_variant_id');

        $returns = DB::table('stocks')
            ->join('order_products', 'stocks.order_product_id', '=', 'order_products.id')
            ->whereNotNull('stocks.order_return_id')->whereNull('stocks.deleted_at')
            ->when($variantId, fn ($q) => $q->where('order_products.product_variant_id', $variantId))
            ->groupBy('order_products.product_variant_id')
            ->select('order_products.product_variant_id', DB::raw('SUM(COALESCE(stocks.inventory_delta, stocks.quantity)) as total'))
            ->pluck('total', 'product_variant_id');
        $incoming = $receipts->map(fn ($row) => (int) $row->total_in);
        $writeoffs = $receipts->map(fn ($row) => (int) $row->total_writeoff);
        foreach ($returns as $id => $delta) {
            $incoming[$id] = ($incoming[$id] ?? 0) + max(0, (int) $delta);
            $writeoffs[$id] = ($writeoffs[$id] ?? 0) + max(0, -(int) $delta);
        }

        return [
            'in'       => $incoming,
            'writeoff' => $writeoffs,
            'out'      => $out,
            'price'    => $receipts->map(fn ($row) => $row->priced_quantity > 0
                ? round($row->priced_value / $row->priced_quantity, 4)
                : null),
        ];
    }

    private function summaryRow(ProductVariant $variant, int $in, int $out, int $writeoff, ?float $avgPrice): array
    {
        $balance = $in - $out - $writeoff;

        return [
            'product_id'         => $variant->product_id,
            'product_variant_id' => $variant->id,
            'code'               => $variant->code,
            'name'               => $variant->product?->name,
            'variant_name'       => $variant->name,
            'unit_value'         => $variant->product?->unit_value,
            'total_in'           => $in,
            'total_out'          => $out,
            'total_writeoff'     => $writeoff,
            'balance'            => $balance,
            // Stav, ktorý reálne rozhoduje o dostupnosti v e-shope.
            'tracked_quantity'   => $variant->quantity,
            'avg_price'          => $avgPrice,
            'stock_value'        => $avgPrice !== null ? round(max(0, $balance) * $avgPrice, 2) : 0,
        ];
    }

    public function show(Stock $stock)
    {
        Gate::authorize('view', $stock);

        return response(new StockResource($stock->load(['shipping.order.customer', 'orderProduct.product', 'productDirect'])));
    }

    public function store(Request $request)
    {
        Gate::authorize('create', Stock::class);

        $isWriteoff = (int) $request->input('quantity') < 0;

        $data = $request->validate([
            // Vymazaný variant sa naskladniť nedá — exists bez podmienky by ho pustil.
            'product_variant_id' => ['required', 'integer', Rule::exists('product_variants', 'id')->whereNull('deleted_at')],
            // Kladné množstvo je príjem, záporné odpis (rozbité, stratené, inventúra).
            'quantity'           => 'required|integer|not_in:0|between:-' . self::MAX_QUANTITY . ',' . self::MAX_QUANTITY,
            // Odpis nákupnú cenu nemá — skreslila by vážený priemer aj hodnotu skladu.
            'price'              => [$isWriteoff ? 'prohibited' : 'nullable', 'numeric', 'min:0', 'max:' . self::MAX_PRICE, 'decimal:0,2'],
            // Odpis bez dôvodu sa pri inventúre nedá spätne vysvetliť.
            'note'               => [$isWriteoff ? 'required' : 'nullable', 'string', 'max:255'],
            'supplier'           => [$isWriteoff ? 'prohibited' : 'nullable', 'string', 'max:255'],
            'document_number'    => 'nullable|string|max:64',
            'received_at'        => 'nullable|date_format:Y-m-d|before_or_equal:today|after_or_equal:' . self::MIN_RECEIVED_AT,
        ], self::MESSAGES, self::ATTRIBUTES);

        $data['received_at'] ??= now()->toDateString();

        // product_id sa dopĺňa z variantu — príjem nesmie skončiť na produkte
        // bez určenia, ktorej skladovej položky sa týka.
        $data['product_id'] = ProductVariant::whereKey($data['product_variant_id'])->value('product_id');

        $stock = DB::transaction(fn () => Stock::create($data));

        return response(new StockResource($stock->load(['productDirect', 'variant'])), 201);
    }

    public function update(Stock $stock, Request $request)
    {
        Gate::authorize('update', $stock);

        // Bez whitelistu by sa cez $request->all() dalo prepísať shipping_id
        // aj deleted_at — model má prázdny $guarded.
        $data = $request->validate([
            'quantity'        => 'sometimes|integer|not_in:0|between:-' . self::MAX_QUANTITY . ',' . self::MAX_QUANTITY,
            'price'           => 'sometimes|nullable|numeric|min:0|max:' . self::MAX_PRICE . '|decimal:0,2',
            'note'            => 'sometimes|nullable|string|max:255',
            'supplier'        => 'sometimes|nullable|string|max:255',
            'document_number' => 'sometimes|nullable|string|max:64',
            'received_at'     => 'sometimes|nullable|date_format:Y-m-d|before_or_equal:today|after_or_equal:' . self::MIN_RECEIVED_AT,
        ], self::MESSAGES, self::ATTRIBUTES);

        DB::transaction(function () use ($stock, $data) {
            $stock = Stock::whereKey($stock->id)->lockForUpdate()->firstOrFail();
            abort_if($stock->stock_receipt_id, 422, 'Položku príjemky nemožno meniť samostatne. Stornujte celý doklad.');
            abort_if($stock->order_id || $stock->shipping_id || $stock->order_return_id, 422, 'Pohyb objednávky upravte cez expedíciu alebo vratku.');
            $stock->update($data);
        });
        $stock->refresh();

        return new StockResource($stock);
    }

    public function destroy(Stock $stock)
    {
        Gate::authorize('delete', $stock);

        DB::transaction(function () use ($stock) {
            $stock = Stock::whereKey($stock->id)->lockForUpdate()->firstOrFail();
            abort_if($stock->stock_receipt_id, 422, 'Položku príjemky nemožno zmazať samostatne. Stornujte celý doklad.');
            abort_if($stock->order_id || $stock->shipping_id || $stock->order_return_id, 422, 'Pohyb objednávky nemožno zmazať zo skladu.');
            $stock->delete();
        });

        return response()->noContent();
    }
}
