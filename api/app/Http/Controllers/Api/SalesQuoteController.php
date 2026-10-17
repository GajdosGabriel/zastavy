<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\OrderRequest;
use App\Models\Customer;
use App\Models\Order;
use App\Models\ProductVariant;
use App\Models\SalesQuote;
use App\Models\SalesQuoteVersion;
use App\Models\ShippingMethod;
use App\Models\User;
use App\Notifications\OrderCreated;
use App\Notifications\QuoteOffered;
use App\Services\CustomerService;
use App\Services\OrderNumberService;
use App\Services\QuoteNumberService;
use App\Services\SalesAccess;
use App\Services\SystemLog\Activity;
use App\Support\QuotePricing;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class SalesQuoteController extends Controller
{
    private const CUSTOMER_FIELDS = ['company' => 200, 'name' => 150, 'email' => 150, 'phone' => 40, 'street' => 250, 'postcode' => 20, 'city' => 100];

    /**
     * Pravidlá údajov zákazníka. Verejný dopyt a odoslanie ponuky vyžadujú
     * fakturačné údaje (z ponuky vznikne objednávka), koncept obsluhy nie.
     */
    private function customerRules(bool $complete, bool $phone = true): array
    {
        $rules = ['customer' => 'required|array:company,name,email,phone,street,postcode,city,ico,dic,ic_dic'];
        foreach (self::CUSTOMER_FIELDS as $field => $max) {
            $required = $complete && ($phone || $field !== 'phone') ? 'required' : 'nullable';
            $rules['customer.'.$field] = $required.'|string|max:'.$max.($field === 'email' ? '|email' : '');
        }
        foreach (['ico', 'dic', 'ic_dic'] as $field) {
            $rules['customer.'.$field] = 'nullable|string|max:30';
        }

        return $rules;
    }

    private function headerRules(bool $complete): array
    {
        return $this->customerRules($complete, false) + [
            'customer_id' => 'nullable|integer|exists:customers,id',
            'title' => 'nullable|string|max:200',
            'brief' => 'nullable|string|max:10000',
            'internal_note' => 'nullable|string|max:10000',
        ];
    }

    public function store(Request $request)
    {
        $data = $request->validate($this->customerRules(true) + ['brief' => 'required|string|max:10000',
            'items' => 'nullable|array|max:100', 'items.*.variant_id' => 'required|integer', 'items.*.quantity' => 'required|integer|min:1|max:100000',
            'attachments' => 'nullable|array|max:5', 'attachments.*' => OrderRequest::attachmentRules()]);
        $paths = [];
        try {
            $result = DB::transaction(function () use ($data, $request, &$paths) {
                $items = [];
                foreach ($data['items'] ?? [] as $item) {
                    $v = ProductVariant::with('product')->find($item['variant_id']);
                    if (! $v?->published || ! $v->product?->published) {
                        throw ValidationException::withMessages(['items' => 'Niektorá položka už nie je dostupná.']);
                    }
                    $items[] = ['variant_id' => $v->id, 'name' => $v->product->name.' / '.$v->name, 'quantity' => $item['quantity']];
                }
                $token = Str::random(64);
                $quote = SalesQuote::create(['uuid' => (string) Str::uuid(), 'number' => app(QuoteNumberService::class)->next(), 'token_hash' => hash('sha256', $token), 'token' => $token,
                    'token_expires_at' => now()->addDays(90), 'customer' => $data['customer'], 'brief' => $data['brief'], 'requested_items' => $items, 'source' => 'web',
                    'user_id' => $request->user('sanctum')?->id]);
                foreach ($request->file('attachments', []) as $file) {
                    $path = $file->store('sales-quotes/'.$quote->id, 'local');
                    if (! $path) {
                        throw new \RuntimeException('Súbor sa nepodarilo uložiť.');
                    } $paths[] = $path;
                    $quote->attachments()->create(['disk' => 'local', 'path' => $path, 'name' => $file->getClientOriginalName(), 'mime' => $file->getMimeType(), 'size' => $file->getSize()]);
                }

                return ['uuid' => $quote->uuid, 'token' => $token];
            });
        } catch (\Throwable $e) {
            Storage::disk('local')->delete($paths);
            throw $e;
        }

        return response()->json($result, 201);
    }

    /** Ponuka založená obsluhou (dopyt telefonicky, e-mailom, osobne). */
    public function create(Request $request)
    {
        SalesAccess::staff($request);
        $data = $request->validate(['customer.company' => 'required_without:customer.name|nullable|string|max:200'] + $this->headerRules(false));

        $quote = DB::transaction(function () use ($data, $request) {
            $token = Str::random(64);

            return SalesQuote::create(['uuid' => (string) Str::uuid(), 'number' => app(QuoteNumberService::class)->next(), 'token_hash' => hash('sha256', $token), 'token' => $token,
                'token_expires_at' => now()->addDays(90), 'customer' => $data['customer'], 'customer_id' => $data['customer_id'] ?? null, 'title' => $data['title'] ?? null,
                'brief' => $data['brief'] ?? null, 'internal_note' => $data['internal_note'] ?? null, 'requested_items' => [], 'source' => 'staff', 'status' => 'draft',
                'created_by' => $request->user()->id]);
        });

        return $this->detail($quote, true)->setStatusCode(201);
    }

    public function index(Request $request)
    {
        SalesAccess::staff($request);
        $f = $request->validate(['status' => 'nullable|in:requested,draft,offered,accepted,withdrawn,expired,expiring', 'search' => 'nullable|string|max:100',
            'source' => 'nullable|in:web,staff', 'customer_id' => 'nullable|integer']);

        return SalesQuote::query()->withExists('draft as has_draft')
            ->when($f['status'] ?? null, function ($q, $status) {
                match ($status) {
                    'expired' => $q->where('status', 'offered')->whereDate('valid_until', '<', today()),
                    'expiring' => $q->where('status', 'offered')->whereDate('valid_until', '>=', today())->whereDate('valid_until', '<=', today()->addDays(7)),
                    'offered' => $q->where('status', 'offered')->where(fn ($q) => $q->whereNull('valid_until')->orWhereDate('valid_until', '>=', today())),
                    default => $q->where('status', $status),
                };
            })
            ->when($f['search'] ?? null, function ($q, $search) {
                $like = '%'.addcslashes($search, '%_\\').'%';
                $q->where(fn ($q) => $q->where('number', 'like', $like)->orWhere('title', 'like', $like)
                    ->orWhere('customer->company', 'like', $like)->orWhere('customer->name', 'like', $like)->orWhere('customer->email', 'like', $like)->orWhere('customer->ico', 'like', $like));
            })
            ->when($f['source'] ?? null, fn ($q, $source) => $q->where('source', $source))
            ->when($f['customer_id'] ?? null, fn ($q, $id) => $q->where('customer_id', $id))
            ->latest('id')->paginate(20)
            ->through(fn (SalesQuote $quote) => $quote->only(['id', 'number', 'status', 'source', 'title', 'customer', 'customer_id', 'version', 'valid_until', 'total_net', 'total_gross',
                'sent_at', 'viewed_at', 'accepted_at', 'created_at', 'has_draft']) + ['is_expired' => $quote->isExpired()]);
    }

    public function show(Request $request, SalesQuote $quote)
    {
        SalesAccess::staff($request);

        return $this->detail($quote, true);
    }

    public function publicShow(Request $request, string $uuid)
    {
        $quote = SalesQuote::where('uuid', $uuid)->firstOrFail();
        SalesAccess::token($request, $quote);
        if ($quote->status === 'offered' && ! $quote->viewed_at) {
            $quote->forceFill(['viewed_at' => now()])->saveQuietly();
        }

        return $this->detail($quote, false);
    }

    private function detail(SalesQuote $quote, bool $staff)
    {
        $quote->refresh();
        $data = $quote->toArray();
        $data['versions'] = $staff ? $quote->versions : $quote->sentVersions;
        $data['is_expired'] = $quote->isExpired();
        $data['has_draft'] = $quote->draft()->exists();
        $data['attachments'] = $quote->attachments->map->only(['id', 'name', 'size']);
        $data['order_uuid'] = $quote->order?->uuid;
        if ($staff) {
            $data['order_serial_number'] = $quote->order?->serial_number;
            $data['creator'] = $quote->creator?->only(['id', 'name']);
        } else {
            unset($data['internal_note'], $data['customer_id'], $data['created_by'], $data['user_id'], $data['sent_to']);
        }

        return response()->json($data)->header('Cache-Control', 'private, no-store');
    }

    public function share(Request $request, SalesQuote $quote)
    {
        SalesAccess::staff($request);

        return DB::transaction(function () use ($quote) {
            $quote = SalesQuote::lockForUpdate()->findOrFail($quote->id);

            return ['uuid' => $quote->uuid, 'token' => $quote->renewToken()];
        });
    }

    /** Uloží hlavičku a koncept; zákazník zmeny neuvidí, kým sa ponuka neodošle. */
    public function update(Request $request, SalesQuote $quote)
    {
        SalesAccess::staff($request);
        $data = $request->validate(['version' => 'required|integer|min:0'] + $this->headerRules(false) + QuotePricing::rules(false));

        return DB::transaction(function () use ($quote, $data, $request) {
            $quote = $this->lockOpen($quote, $data['version']);
            $this->saveDraft($quote, $data, false, $request->user());

            return $this->detail($quote, true);
        });
    }

    /** Uloží a odošle novú verziu ponuky; voliteľne pošle zákazníkovi e-mail s odkazom. */
    public function offer(Request $request, SalesQuote $quote)
    {
        SalesAccess::staff($request);
        $data = $request->validate(['version' => 'required|integer|min:0', 'notify' => 'sometimes|boolean'] + $this->headerRules(true) + QuotePricing::rules(true));

        return DB::transaction(function () use ($quote, $data, $request) {
            $quote = $this->lockOpen($quote, $data['version']);
            $draft = $this->saveDraft($quote, $data, true, $request->user());
            $notify = (bool) ($data['notify'] ?? false);
            $draft->update(['sent_at' => now()]);
            $quote->update(['version' => $draft->version, 'status' => 'offered', 'valid_until' => $draft->valid_until, 'total_net' => $draft->totals['net'],
                'total_gross' => $draft->totals['gross'], 'sent_at' => now(), 'viewed_at' => null, 'sent_to' => $notify ? $quote->customer['email'] : $quote->sent_to]);
            if ($notify) {
                Notification::route('mail', [$quote->customer['email'] => $quote->customer['name'] ?? null])->notify((new QuoteOffered($quote))->afterCommit());
            }
            Activity::record('quote', 'sent', 'Odoslaná cenová ponuka '.$quote->number.' (verzia '.$draft->version.')',
                ['quote_id' => $quote->id, 'version' => $draft->version, 'emailed' => $notify, 'total_gross' => $draft->totals['gross']]);

            return $this->detail($quote, true);
        });
    }

    public function discardDraft(Request $request, SalesQuote $quote)
    {
        SalesAccess::staff($request);

        return DB::transaction(function () use ($quote) {
            $quote = SalesQuote::lockForUpdate()->findOrFail($quote->id);
            $quote->draft()->delete();

            return $this->detail($quote, true);
        });
    }

    private function lockOpen(SalesQuote $quote, int $version): SalesQuote
    {
        $quote = SalesQuote::lockForUpdate()->findOrFail($quote->id);
        abort_if($quote->isClosed() || $quote->version != $version, 409, 'Ponuka sa zmenila alebo je uzavretá. Obnovte detail.');

        return $quote;
    }

    private function saveDraft(SalesQuote $quote, array $data, bool $strict, User $user): SalesQuoteVersion
    {
        $quote->fill(collect($data)->only(['customer', 'customer_id', 'title', 'brief', 'internal_note'])->all())->save();
        $items = QuotePricing::items($data['items'] ?? [], $strict);
        $shipping = QuotePricing::shipping($data['shipping_method_id'] ?? null, $data['shipping_price_net'] ?? 0, $strict);
        $discount = (float) ($data['discount_percent'] ?? 0);
        $attributes = ['items' => $items, 'shipping' => $shipping, 'discount_percent' => $discount, 'totals' => QuotePricing::totals($items, $discount, $shipping),
            'terms' => $data['terms'] ?? null, 'valid_until' => $data['valid_until'] ?? null, 'created_by' => $user->id];
        $draft = $quote->draft()->first();
        if ($draft) {
            $draft->update($attributes);

            return $draft;
        }

        return $quote->versions()->create($attributes + ['version' => $quote->version + 1]);
    }

    public function withdraw(Request $request, SalesQuote $quote)
    {
        SalesAccess::staff($request);

        return DB::transaction(function () use ($quote) {
            $quote = SalesQuote::lockForUpdate()->findOrFail($quote->id);
            abort_if($quote->order_id, 409);
            $quote->update(['status' => 'withdrawn']);

            return $this->detail($quote, true);
        });
    }

    public function accept(Request $request, string $uuid)
    {
        $data = $request->validate(['version' => 'required|integer|min:1', 'name' => 'required|string|max:150', 'confirm' => 'required|accepted']);

        return DB::transaction(function () use ($uuid, $request, $data) {
            $quote = SalesQuote::where('uuid', $uuid)->lockForUpdate()->firstOrFail();
            SalesAccess::token($request, $quote);
            abort_if($quote->version != $data['version'], 409, 'Ponuka má novšiu verziu.');
            if ($quote->order_id) {
                return ['order_uuid' => $quote->order->uuid];
            }
            $version = $quote->sentVersions()->firstOrFail();
            abort_unless($quote->status === 'offered' && $version->valid_until?->endOfDay()->isFuture(), 409, 'Ponuka už nie je platná.');
            // Katalógové ceny sa môžu zmeniť; záväzný je uložený odtlačok prijatej ponuky.
            foreach ($version->items as $item) {
                abort_unless(! empty($item['is_custom']) || ProductVariant::whereKey($item['variant_id'])->where('product_id', $item['product_id'])->whereHas('product')->exists(), 409, 'Položka bola vyradená. Vyžiadajte novú ponuku.');
            }
            abort_unless(ShippingMethod::whereKey($version->shipping['id'] ?? null)->where('active', true)->exists(), 409, 'Doprava bola vyradená. Vyžiadajte novú ponuku.');
            $customers = app(CustomerService::class);
            $known = $quote->customer_id ? Customer::find($quote->customer_id) : null;
            [$customer, $contact] = $known ? [$known, $customers->storeUser($known, $quote->customer)] : $customers->handleCheckout($quote->customer);
            $actor = User::find($quote->user_id);
            $notes = collect($version->items)->filter(fn ($i) => ! empty($i['description']))->map(fn ($i) => $i['snapshot']['name'].': '.$i['description']);
            $order = Order::create(['customer_id' => $customer->id, 'user_id' => $actor?->isActive() ? $actor->id : $contact?->id,
                'billing_snapshot' => $quote->customer, 'name' => $quote->customer['name'], 'email' => $quote->customer['email'], 'phone' => $quote->customer['phone'] ?? null,
                'shipping_method_id' => $version->shipping['id'], 'shipping_method_name' => $version->shipping['name'], 'shipping_price' => $version->shipping['price'],
                'price_adjustment' => $version->discount_percent > 0 ? ['direction' => 'discount', 'type' => 'percent', 'value' => $version->discount_percent, 'label' => 'Zľava z cenovej ponuky'] : null,
                'note' => trim('Cenová ponuka '.($quote->number ?? $quote->id).', verzia '.$quote->version."\n".$notes->implode("\n")."\n".($version->terms ?? ''))]);
            $order->update(['serial_number' => app(OrderNumberService::class)->next(now()->format('Y-m'))]);
            foreach ($version->items as $item) {
                $order->orderProducts()->create(['product_id' => $item['product_id'], 'product_variant_id' => $item['variant_id'], 'is_custom' => ! empty($item['is_custom']),
                    'product_snapshot' => $item['snapshot'], 'variant_label' => $item['snapshot']['variant_name'], 'quantity' => $item['quantity'], 'price' => $item['price'],
                    'total' => $item['line_gross'] ?? round($item['quantity'] * $item['price'], 2)]);
            }
            $quote->update(['order_id' => $order->id, 'status' => 'accepted', 'accepted_at' => now(), 'accepted_by' => $data['name'], 'customer_id' => $customer->id]);
            $order->notifyCustomer(new OrderCreated($order));
            Notification::send(User::role('super-admin')->get(), (new OrderCreated($order))->afterCommit());

            return ['order_uuid' => $order->uuid];
        }, 3);
    }

    public function file(Request $request, string $uuid, int $attachment)
    {
        $quote = SalesQuote::where('uuid', $uuid)->firstOrFail();
        if ($request->header('X-Sales-Token')) {
            SalesAccess::token($request, $quote);
        } else {
            SalesAccess::staff($request);
        }
        $file = $quote->attachments()->findOrFail($attachment);

        return SalesAccess::download($file->disk, $file->path, $file->name);
    }
}
