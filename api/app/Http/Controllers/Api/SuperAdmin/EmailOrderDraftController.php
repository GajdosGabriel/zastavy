<?php

namespace App\Http\Controllers\Api\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Jobs\ProcessEmailOrder;
use App\Models\EmailOrderDraft;
use App\Services\EmailOrders\EmailOrderInbox;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

/**
 * Objednávky a dopyty z e-mailu (EmailOrderDraft) pre obsluhu: zoznam, detail
 * s pôvodným mailom a návrhom AI, označenie „nie je objednávka“ / zahodiť
 * a opätovné spracovanie. Objednávka z návrhu vzniká v košíku, dopyt je
 * rovno cenovou ponukou.
 */
class EmailOrderDraftController extends Controller
{
    /** Stavy v záložkách zoznamu */
    private const TABS = [
        'open' => [EmailOrderDraft::PENDING, EmailOrderDraft::DRAFT, EmailOrderDraft::FAILED],
        'created' => [EmailOrderDraft::CREATED],
        'other' => [EmailOrderDraft::NOT_ORDER, EmailOrderDraft::DISMISSED],
    ];

    public function index(Request $request)
    {
        $this->authorizeSuperAdmin($request);
        $tab = array_key_exists($request->query('tab'), self::TABS) ? $request->query('tab') : 'open';

        $drafts = EmailOrderDraft::whereIn('status', self::TABS[$tab])
            ->with(['order:id,serial_number', 'salesQuote:id,number'])->withCount('attachments')
            ->latest('received_at')->latest('id')->paginate(30);

        return response()->json([
            'data' => $drafts->getCollection()->map(fn (EmailOrderDraft $d) => $this->summary($d)),
            'meta' => ['current_page' => $drafts->currentPage(), 'last_page' => $drafts->lastPage(), 'total' => $drafts->total()],
            'counts' => collect(self::TABS)->map(fn ($statuses) => EmailOrderDraft::whereIn('status', $statuses)->count()),
        ]);
    }

    public function show(Request $request, EmailOrderDraft $draft)
    {
        $this->authorizeSuperAdmin($request);
        $draft->load(['order:id,serial_number', 'salesQuote:id,number', 'attachments']);

        return response()->json(['data' => [
            ...$this->summary($draft),
            'body' => $draft->body,
            'preview' => $draft->preview,
            'reasons' => $draft->reasons ?? [],
            'error' => $draft->error,
            'attachments' => $draft->attachments->map(fn ($a) => [
                'id' => $a->id, 'name' => $a->name, 'mime' => $a->mime, 'size' => $a->size,
            ]),
        ]]);
    }

    /** Obsluha: nie je objednávka / zahodiť / vrátiť medzi otvorené. */
    public function update(Request $request, EmailOrderDraft $draft)
    {
        $this->authorizeSuperAdmin($request);
        $data = $request->validate(['status' => ['required', 'in:not_order,dismissed,draft']]);
        abort_if($draft->order_id || $draft->sales_quote_id, 422, 'Z tohto e-mailu už vznikla objednávka alebo ponuka.');
        abort_if($data['status'] === EmailOrderDraft::DRAFT && ! $draft->preview, 422, 'E-mail nemá návrh – spracujte ho znova.');

        $draft->update(['status' => $data['status']]);

        return $this->show($request, $draft);
    }

    /** Návrh objednávky je v skutočnosti dopyt – vznikne cenová ponuka ako pri automatickom dopyte. */
    public function quote(Request $request, EmailOrderDraft $draft, EmailOrderInbox $inbox)
    {
        $this->authorizeSuperAdmin($request);
        abort_if($draft->order_id || $draft->sales_quote_id, 422, 'Z tohto e-mailu už vznikla objednávka alebo ponuka.');
        abort_unless($draft->preview, 422, 'E-mail nemá návrh – spracujte ho znova.');

        $inbox->createQuote($draft, auto: false);

        return $this->show($request, $draft->refresh());
    }

    /** Znova cez AI – napr. po výpadku alebo keď AI mail chybne neoznačila za objednávku. */
    public function retry(Request $request, EmailOrderDraft $draft)
    {
        $this->authorizeSuperAdmin($request);
        abort_if($draft->order_id || $draft->sales_quote_id, 422, 'Z tohto e-mailu už vznikla objednávka alebo ponuka.');
        abort_unless(config('services.openai.key'), 503, 'AI import vyžaduje nastavený OPENAI_API_KEY na serveri.');

        $draft->update(['status' => EmailOrderDraft::PENDING, 'error' => null]);
        ProcessEmailOrder::dispatch($draft->id);

        return response()->json(['message' => 'E-mail sa spracúva, návrh bude o chvíľu pripravený.'], 202);
    }

    public function attachment(Request $request, EmailOrderDraft $draft, int $attachment)
    {
        $this->authorizeSuperAdmin($request);
        $file = $draft->attachments()->findOrFail($attachment);

        return Storage::disk($file->disk)->download($file->path, $file->name);
    }

    private function summary(EmailOrderDraft $d): array
    {
        $preview = $d->preview ?? [];

        return [
            'id' => $d->id,
            'status' => $d->status,
            'from_email' => $d->from_email,
            'from_name' => $d->from_name,
            'subject' => $d->subject,
            'received_at' => $d->received_at?->format('d.m.Y H:i'),
            'customer' => $preview['customer']['company'] ?? null,
            'customer_status' => $preview['customer_status'] ?? null,
            'items_count' => count($preview['items'] ?? []),
            'items_matched' => collect($preview['items'] ?? [])->filter(fn ($i) => ! empty($i['cart']))->count(),
            'reasons_count' => count($d->reasons ?? []),
            'attachments_count' => $d->attachments_count ?? $d->attachments()->count(),
            'auto_created' => $d->auto_created,
            'order' => $d->order ? ['id' => $d->order->id, 'serial_number' => $d->order->serial_number] : null,
            'quote' => $d->salesQuote ? ['id' => $d->salesQuote->id, 'number' => $d->salesQuote->number] : null,
        ];
    }

    private function authorizeSuperAdmin(Request $request): void
    {
        abort_unless($request->user()?->hasRole('super-admin'), 403);
    }
}
