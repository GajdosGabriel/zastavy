<?php

namespace App\Http\Controllers\Api\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Http\Resources\StockReceiptResource;
use App\Models\ProductVariant;
use App\Models\Stock;
use App\Models\StockReceipt;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class StockReceiptController extends Controller
{
    public function index(Request $request)
    {
        Gate::authorize('viewAny', Stock::class);
        $data = $request->validate(['search' => 'nullable|string|max:255', 'status' => 'nullable|in:active,cancelled']);
        $query = StockReceipt::with('items')->latest('id');
        if ($search = $data['search'] ?? null) {
            $query->where(fn ($q) => $q->where('number', 'like', '%' . $search . '%')
                ->orWhere('supplier', 'like', '%' . $search . '%')
                ->orWhere('document_number', 'like', '%' . $search . '%'));
        }
        if (($data['status'] ?? null) === 'active') $query->whereNull('cancelled_at');
        if (($data['status'] ?? null) === 'cancelled') $query->whereNotNull('cancelled_at');
        return StockReceiptResource::collection($query->paginate(20)->withQueryString());
    }

    public function store(Request $request)
    {
        Gate::authorize('create', Stock::class);
        $data = $request->validate([
            'uuid' => 'required|uuid',
            'received_at' => 'required|date_format:Y-m-d|after_or_equal:2000-01-01|before_or_equal:today',
            'supplier' => 'required|string|max:255',
            'supplier_address' => 'nullable|string|max:255',
            'supplier_ico' => 'nullable|string|max:32',
            'supplier_dic' => 'nullable|string|max:32',
            'supplier_vat_id' => 'nullable|string|max:32',
            'document_number' => 'nullable|string|max:64',
            'warehouse' => 'required|string|max:255',
            'received_by' => 'required|string|max:255',
            'note' => 'nullable|string|max:2000',
            'items' => 'required|array|min:1|max:100',
            'items.*.product_variant_id' => ['required', 'integer', 'distinct', Rule::exists('product_variants', 'id')->whereNull('deleted_at')],
            'items.*.quantity' => 'required|integer|between:1,100000',
            'items.*.price' => 'required|numeric|between:0,99999.99|decimal:0,2',
            'items.*.discount' => 'required|numeric|between:0,100|decimal:0,2',
            'items.*.vat' => 'required|numeric|between:0,100|decimal:0,2',
            'items.*.note' => 'nullable|string|max:255',
        ], [
            'items.*.product_variant_id.distinct' => 'Rovnakú skladovú položku pridajte iba raz a upravte jej množstvo.',
            'items.*.product_variant_id.exists' => 'Vybraná skladová položka už neexistuje.',
            'items.*.quantity.between' => 'Množstvo musí byť od 1 do 100 000.',
            'items.*.price.required' => 'Zadajte nákupnú cenu bez DPH (aj nulovú).',
        ], [
            'supplier' => 'dodávateľ', 'warehouse' => 'sklad', 'received_by' => 'prevzal',
            'received_at' => 'dátum príjmu', 'items' => 'položky',
            'items.*.quantity' => 'množstvo', 'items.*.price' => 'cena bez DPH',
            'items.*.vat' => 'DPH', 'items.*.discount' => 'zľava',
        ]);

        $receipt = DB::transaction(function () use ($data, $request) {
            $items = $data['items'];
            unset($data['items']);
            // Rovnaký pokus po výpadku spojenia nesmie naskladniť tovar druhýkrát.
            $receipt = StockReceipt::firstOrCreate(['uuid' => $data['uuid']], array_merge($data, [
                'created_by' => $request->user()->id,
                'created_by_name' => $request->user()->name ?? $request->user()->username ?? 'Administrátor',
            ]));
            if (!$receipt->wasRecentlyCreated) {
                return $receipt;
            }
            $receipt->update(['number' => 'PR-' . now()->format('Y') . '-' . str_pad((string) $receipt->id, 6, '0', STR_PAD_LEFT)]);

            // Jednotné poradie zámkov predchádza deadlocku pri súbežných dokladoch.
            $variants = ProductVariant::with('product')->whereIn('id', array_column($items, 'product_variant_id'))
                ->orderBy('id')->lockForUpdate()->get()->keyBy('id');
            foreach ($items as $index => $item) {
                $variant = $variants->get($item['product_variant_id']);
                if (!$variant || !$variant->product) {
                    throw ValidationException::withMessages(["items.$index.product_variant_id" => 'Vybraná položka už nie je dostupná.']);
                }
                Stock::create([
                    'stock_receipt_id' => $receipt->id,
                    'product_id' => $variant->product_id,
                    'product_variant_id' => $variant->id,
                    'quantity' => $item['quantity'],
                    'price' => round($item['price'] * (1 - $item['discount'] / 100), 6),
                    'receipt_unit_price' => $item['price'],
                    'receipt_discount' => $item['discount'],
                    'receipt_vat' => $item['vat'],
                    'receipt_item_snapshot' => [
                        'name' => $variant->product->name,
                        'variant_name' => $variant->name,
                        'code' => $variant->code,
                        'unit' => $variant->product->unit_value,
                    ],
                    'note' => $item['note'] ?? null,
                    'supplier' => $receipt->supplier,
                    'document_number' => $receipt->document_number,
                    'received_at' => $receipt->received_at,
                ]);
            }
            return $receipt;
        }, 3);

        return (new StockReceiptResource($receipt->load('items')))->response()->setStatusCode(201);
    }

    public function show(StockReceipt $receipt)
    {
        Gate::authorize('viewAny', Stock::class);
        return new StockReceiptResource($receipt->load('items'));
    }

    public function cancel(Request $request, StockReceipt $receipt)
    {
        $data = $request->validate(['reason' => 'required|string|max:255']);
        DB::transaction(function () use ($receipt, $request, $data) {
            $receipt = StockReceipt::whereKey($receipt->id)->lockForUpdate()->firstOrFail();
            $items = $receipt->items()->get();
            foreach ($items as $item) {
                Gate::authorize('delete', $item);
            }
            if ($receipt->cancelled_at) return;
            ProductVariant::withTrashed()->whereIn('id', $items->pluck('product_variant_id'))
                ->orderBy('id')->lockForUpdate()->get();
            foreach ($items as $item) {
                $item->delete();
            }
            $receipt->update([
                'cancelled_at' => now(), 'cancelled_by' => $request->user()->id,
                'cancellation_reason' => $data['reason'],
            ]);
        }, 3);
        return new StockReceiptResource($receipt->fresh()->load('items'));
    }
}
