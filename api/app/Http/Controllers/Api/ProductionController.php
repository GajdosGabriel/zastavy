<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\OrderRequest;
use App\Models\ArtworkVersion;
use App\Models\Order;
use App\Models\OrderMessage;
use App\Models\OrderProduction;
use App\Models\User;
use App\Notifications\OrderMessageReceived;
use App\Notifications\OrderMessageSent;
use App\Services\SalesAccess;
use App\Support\OrderThread;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;

class ProductionController extends Controller
{
    public function index(Request $request)
    {
        SalesAccess::staff($request);
        // Záznamy, ktoré vznikli len kvôli konverzácii, do plánu výroby nepatria.
        $query = OrderProduction::with('order')->where('planned', true)->whereHas('order', function ($q) use ($request) {
            if (! $request->user()->hasRole('super-admin')) {
                $q->where(function ($q) use ($request) {
                    $q->where('user_id', $request->user()->id);
                    if ($request->user()->customer_id) {
                        $q->orWhere('customer_id', $request->user()->customer_id);
                    }
                });
            }
        });
        if ($request->boolean('overdue')) {
            $query->where('status', '!=', 'completed')->where(function ($q) {
                $q->whereDate('production_due_at', '<', today())->orWhereDate('delivery_due_at', '<', today());
            });
        }
        if ($request->filled('status')) {
            $query->where('status', $request->string('status')->toString());
        }
        $page = $query->orderByRaw('production_due_at IS NULL')->orderBy('production_due_at')->paginate(20);
        $page->through(fn ($p) => \Illuminate\Support\Arr::only($p->toArray(), ['id', 'order_id', 'status', 'production_due_at', 'delivery_due_at', 'materials', 'note']) + ['serial_number' => $p->order->serial_number, 'company' => $p->order->billing->company, 'overdue' => $this->overdue($p)]);

        return $page;
    }

    private function overdue($p): bool
    {
        return $p->status !== 'completed' && (($p->production_due_at && $p->production_due_at->lt(today())) || ($p->delivery_due_at && $p->delivery_due_at->lt(today())));
    }

    public function show(Order $order)
    {
        Gate::authorize('update', $order);
        OrderMessage::where('order_id', $order->id)->where('direction', 'customer')->whereNull('read_at')->update(['read_at' => now()]);

        return $this->detail($order);
    }

    private function detail(Order $order, bool $public = false)
    {
        $p = OrderProduction::where('order_id', $order->id)->first();
        $production = $p ? \Illuminate\Support\Arr::only($p->toArray(), ['status', 'production_due_at', 'delivery_due_at']) : null;
        if ($p && ! $public) {
            $production += ['materials' => $p->materials, 'note' => $p->note, 'overdue' => $this->overdue($p)];
        }

        return response()->json(['uuid' => $order->uuid, 'serial_number' => $order->serial_number, 'production' => $production, 'versions' => ArtworkVersion::where('order_id', $order->id)->orderByDesc('version')->get(), 'messages' => $this->messages($order)])->header('Cache-Control', 'private, no-store');
    }

    public function update(Request $request, Order $order)
    {
        Gate::authorize('update', $order);
        $data = $request->validate(['status' => 'required|in:awaiting_artwork,ready,in_progress,completed', 'production_due_at' => 'nullable|date_format:Y-m-d',
            'delivery_due_at' => ['nullable', 'date_format:Y-m-d', ...($request->filled('production_due_at') ? ['after_or_equal:production_due_at'] : [])], 'materials' => 'nullable|string|max:2000', 'note' => 'nullable|string|max:5000']);

        return DB::transaction(function () use ($order, $data) {
            $order = Order::lockForUpdate()->findOrFail($order->id);
            abort_if($order->isFinished() || $order->isStorned() || $order->isArchived(), 409, 'Objednávka je uzavretá.');
            $p = OrderProduction::firstOrCreate(['order_id' => $order->id]);
            $latest = ArtworkVersion::where('order_id', $order->id)->orderByDesc('version')->first();
            if ($data['status'] !== 'awaiting_artwork') {
                abort_unless($latest?->status === 'approved', 409, 'Najprv musí zákazník schváliť aktuálnu verziu grafiky.');
            }
            $allowed = ['awaiting_artwork' => ['awaiting_artwork', 'ready'], 'ready' => ['ready', 'in_progress'], 'in_progress' => ['in_progress', 'completed'], 'completed' => ['completed']];
            abort_unless(in_array($data['status'], $allowed[$p->status], true), 409, 'Nepovolený prechod výroby.');
            $p->update($data + ['planned' => true]);

            return $this->detail($order);
        });
    }

    public function upload(Request $request, Order $order)
    {
        Gate::authorize('update', $order);
        $request->validate(['file' => 'required|file|max:10240|mimes:pdf,png,jpg,jpeg,webp', 'note' => 'nullable|string|max:5000']);
        $path = null;
        try {
            return DB::transaction(function () use ($order, $request, &$path) {
                $order = Order::lockForUpdate()->findOrFail($order->id);
                abort_if($order->isFinished() || $order->isStorned() || $order->isArchived(), 409, 'Objednávka je uzavretá.');
                $p = OrderProduction::firstOrCreate(['order_id' => $order->id]);
                abort_if(in_array($p->status, ['in_progress', 'completed']), 409, 'Výroba už začala; návrh nemožno nahradiť.');
                $file = $request->file('file');
                $path = $file->store('artworks/'.$order->id, 'local');
                if (! $path) {
                    throw new \RuntimeException('Súbor sa nepodarilo uložiť.');
                }
                $version = ArtworkVersion::where('order_id', $order->id)->max('version') + 1;
                ArtworkVersion::create(['order_id' => $order->id, 'version' => $version, 'disk' => 'local', 'path' => $path, 'name' => $file->getClientOriginalName(),
                    'sha256' => hash_file('sha256', $file->getRealPath()), 'note' => $request->input('note'), 'created_by' => $request->user()->id]);
                $p->update(['status' => 'awaiting_artwork', 'planned' => true]);

                // Odkaz sa nemení: schválenie je viazané na číslo verzie a staršie e-maily majú ostať funkčné.
                return ['uuid' => $order->uuid, 'token' => $p->shareToken(), 'version' => $version];
            });
        } catch (\Throwable $e) {
            if ($path) {
                Storage::disk('local')->delete($path);
            }throw $e;
        }
    }

    public function share(Order $order)
    {
        Gate::authorize('update', $order);

        return DB::transaction(function () use ($order) {
            Order::lockForUpdate()->findOrFail($order->id);
            $p = OrderProduction::where('order_id', $order->id)->firstOrFail();

            return ['uuid' => $order->uuid, 'token' => $p->renewToken()];
        });
    }

    private function publicOrder(Request $request, string $uuid): Order
    {
        $order = Order::where('uuid', $uuid)->firstOrFail();
        $p = OrderProduction::where('order_id', $order->id)->firstOrFail();
        SalesAccess::token($request, $p);

        return $order;
    }

    public function publicShow(Request $request, string $uuid)
    {
        return $this->detail($this->publicOrder($request, $uuid), true);
    }

    public function decision(Request $request, string $uuid)
    {
        $data = $request->validate(['version' => 'required|integer|min:1', 'action' => 'required|in:approve,comment', 'name' => 'required|string|max:150', 'comment' => 'required_if:action,comment|nullable|string|max:5000', 'confirm' => 'exclude_unless:action,approve|required|accepted']);

        return DB::transaction(function () use ($request, $uuid, $data) {
            $order = Order::where('uuid', $uuid)->lockForUpdate()->firstOrFail();
            $this->publicOrder($request, $uuid);
            abort_if($order->isFinished() || $order->isStorned() || $order->isArchived(), 409, 'Objednávka je uzavretá.');
            $p = OrderProduction::where('order_id', $order->id)->firstOrFail();
            $art = ArtworkVersion::where('order_id', $order->id)->orderByDesc('version')->firstOrFail();
            abort_if($art->version != $data['version'], 409, 'Existuje novšia verzia návrhu.');
            if ($data['action'] === 'approve' && $art->status === 'approved') {
                return $this->detail($order, true);
            }
            abort_if(in_array($p->status, ['in_progress', 'completed']) || $art->status === 'approved', 409, 'Návrh je už schválený.');
            if ($data['action'] === 'approve') {
                $art->update(['status' => 'approved', 'approved_at' => now(), 'approved_by' => $data['name'], 'approved_email' => $order->billing->email]);
                $p->update(['status' => 'ready']);
            } else {
                $art->update(['status' => 'changes_requested']);
            }
            $this->notifyStaff(OrderMessage::create(['order_id' => $order->id, 'direction' => 'customer', 'kind' => $data['action'] === 'approve' ? 'approved' : 'changes_requested',
                'author' => $data['name'], 'body' => $data['action'] === 'approve' ? null : $data['comment'], 'artwork_version_id' => $art->id]));

            return $this->detail($order, true);
        }, 3);
    }

    public function file(Request $request, string $uuid, int $version)
    {
        $order = Order::where('uuid', $uuid)->firstOrFail();
        if ($request->header('X-Sales-Token')) {
            $this->publicOrder($request, $uuid);
        } else {
            Gate::forUser($request->user('sanctum'))->authorize('update', $order);
        }
        $art = ArtworkVersion::where('order_id',$order->id)->where('version',$version)->firstOrFail();

        return SalesAccess::download($art->disk,$art->path,$art->name);
    }

    private function messages(Order $order): array
    {
        return OrderMessage::where('order_id', $order->id)->with('attachments', 'artworkVersion')->orderBy('id')->get()->map(fn ($m) => [
            'id' => $m->id, 'direction' => $m->direction, 'kind' => $m->kind, 'author' => $m->author, 'body' => $m->body,
            'version' => $m->artworkVersion?->version, 'created_at' => $m->created_at,
            'attachments' => $m->attachments->map->only(['id', 'name', 'size'])->values(),
        ])->all();
    }

    private function messageRules(): array
    {
        return ['body' => 'required|string|max:5000', 'files' => 'nullable|array|max:'.config('media.attachments.max_files'), 'files.*' => OrderRequest::attachmentRules()];
    }

    /** Prílohy správ sú súkromné rovnako ako návrhy grafiky; sťahujú sa len cez messageFile(). */
    private function storeFiles(Request $request, OrderMessage $message, array &$paths): void
    {
        foreach ($request->file('files', []) as $file) {
            $path = $file->store('order-messages/'.$message->order_id, 'local');
            if (! $path) {
                throw new \RuntimeException('Súbor sa nepodarilo uložiť.');
            }
            $paths[] = $path;
            $message->attachments()->create(['disk' => 'local', 'path' => $path, 'name' => $file->getClientOriginalName(), 'mime' => $file->getMimeType(), 'size' => $file->getSize(), 'user_id' => $request->user('sanctum')?->id]);
        }
    }

    private function notifyStaff(OrderMessage $message): void
    {
        Notification::send(User::role('super-admin')->get(), (new OrderMessageReceived($message))->afterCommit());
    }

    /** Správa obsluhy zákazníkovi; s `artwork_version` zároveň odošle aktuálny návrh na schválenie. */
    public function message(Request $request, Order $order)
    {
        Gate::authorize('update', $order);
        $data = $request->validate($this->messageRules() + ['artwork_version' => 'nullable|integer|min:1']);
        $paths = [];
        try {
            return DB::transaction(function () use ($order, $request, $data, &$paths) {
                $order = Order::lockForUpdate()->findOrFail($order->id);
                abort_if($order->isArchived(), 409, 'Objednávka je archivovaná.');
                abort_unless($order->routeNotificationForMail(), 422, 'Objednávka nemá e-mail zákazníka.');
                $art = null;
                if (! empty($data['artwork_version'])) {
                    $art = ArtworkVersion::where('order_id', $order->id)->orderByDesc('version')->first();
                    abort_unless($art && $art->version == $data['artwork_version'], 409, 'Existuje novšia verzia návrhu.');
                }
                OrderProduction::firstOrCreate(['order_id' => $order->id], ['planned' => false]);
                $message = OrderMessage::create(['order_id' => $order->id, 'direction' => 'staff', 'kind' => $art ? 'artwork_sent' : 'message',
                    'author' => $request->user()->name ?: OrderThread::FROM_NAME, 'user_id' => $request->user()->id, 'body' => $data['body'], 'artwork_version_id' => $art?->id]);
                $this->storeFiles($request, $message, $paths);
                $order->notifyCustomer(new OrderMessageSent($message));

                return $this->detail($order);
            });
        } catch (\Throwable $e) {
            Storage::disk('local')->delete($paths);
            throw $e;
        }
    }

    /** Správa zákazníka cez súkromný odkaz — k čomukoľvek v objednávke, nielen ku grafike. */
    public function publicMessage(Request $request, string $uuid)
    {
        $data = $request->validate($this->messageRules() + ['name' => 'required|string|max:150']);
        $paths = [];
        try {
            return DB::transaction(function () use ($request, $uuid, $data, &$paths) {
                $order = $this->publicOrder($request, $uuid);
                abort_if($order->isArchived(), 409, 'Objednávka je archivovaná.');
                $message = OrderMessage::create(['order_id' => $order->id, 'direction' => 'customer', 'kind' => 'message', 'author' => $data['name'], 'body' => $data['body']]);
                $this->storeFiles($request, $message, $paths);
                $this->notifyStaff($message);

                return $this->detail($order, true);
            });
        } catch (\Throwable $e) {
            Storage::disk('local')->delete($paths);
            throw $e;
        }
    }

    public function messageFile(Request $request, string $uuid, int $message, int $attachment)
    {
        $order = Order::where('uuid', $uuid)->firstOrFail();
        if ($request->header('X-Sales-Token')) {
            $this->publicOrder($request, $uuid);
        } else {
            Gate::forUser($request->user('sanctum'))->authorize('update', $order);
        }
        $file = OrderMessage::where('order_id', $order->id)->findOrFail($message)->attachments()->findOrFail($attachment);

        return SalesAccess::download($file->disk, $file->path, $file->name);
    }

    /** Objednávky, v ktorých čaká neprečítaná správa zákazníka. */
    public function unread(Request $request)
    {
        SalesAccess::staff($request);
        $rows = OrderMessage::unreadFor($request->user())->selectRaw('order_id, count(*) as unread, max(created_at) as last_at')->groupBy('order_id')->orderByDesc('last_at')->limit(50)->get();
        $orders = Order::whereIn('id', $rows->pluck('order_id'))->get()->keyBy('id');

        return $rows->map(fn ($row) => ['order_id' => $row->order_id, 'serial_number' => $orders[$row->order_id]->serial_number, 'company' => $orders[$row->order_id]->billing->company,
            'unread' => (int) $row->unread, 'last_at' => $row->last_at])->values();
    }
}
