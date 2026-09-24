<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\OrderRequest;
use App\Models\Order;
use App\Models\ProductVariant;
use App\Models\SalesQuote;
use App\Models\ShippingMethod;
use App\Models\User;
use App\Notifications\OrderCreated;
use App\Services\CustomerService;
use App\Services\OrderNumberService;
use App\Services\SalesAccess;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class SalesQuoteController extends Controller
{
    public function store(Request $request)
    {
        $rules = ['customer' => 'required|array:company,name,email,phone,street,postcode,city,ico,dic,ic_dic', 'brief' => 'required|string|max:10000',
            'items' => 'nullable|array|max:100', 'items.*.variant_id' => 'required|integer', 'items.*.quantity' => 'required|integer|min:1|max:100000',
            'attachments' => 'nullable|array|max:5', 'attachments.*' => OrderRequest::attachmentRules()];
        foreach (['company' => 200, 'name' => 150, 'email' => 150, 'phone' => 40, 'street' => 250, 'postcode' => 20, 'city' => 100] as $field => $max) {
            $rules['customer.'.$field] = 'required|string|max:'.$max.($field === 'email' ? '|email' : '');
        }
        foreach (['ico', 'dic', 'ic_dic'] as $field) {
            $rules['customer.'.$field] = 'nullable|string|max:30';
        }
        $data = $request->validate($rules);
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
                $quote = SalesQuote::create(['uuid' => (string) Str::uuid(), 'token_hash' => hash('sha256', $token), 'token_expires_at' => now()->addDays(90),
                    'customer' => $data['customer'], 'brief' => $data['brief'], 'requested_items' => $items, 'user_id' => $request->user('sanctum')?->id]);
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

    public function index(Request $request)
    {
        SalesAccess::staff($request);

        return SalesQuote::latest()->paginate(20);
    }

    public function show(Request $request, SalesQuote $quote)
    {
        SalesAccess::staff($request);

        return $this->detail($quote);
    }

    public function publicShow(Request $request, string $uuid)
    {
        $quote = SalesQuote::where('uuid', $uuid)->firstOrFail();
        SalesAccess::token($request, $quote);

        return $this->detail($quote);
    }

    private function detail(SalesQuote $quote)
    {
        $data = $quote->toArray();
        $data['versions'] = $quote->versions;
        $data['is_expired'] = $quote->status === 'offered' && $quote->versions->first()?->valid_until->copy()->endOfDay()->isPast();
        $data['attachments'] = $quote->attachments->map->only(['id', 'name', 'size']);
        $data['order_uuid'] = $quote->order?->uuid;

        return response()->json($data)->header('Cache-Control', 'private, no-store');
    }

    public function share(Request $request, SalesQuote $quote)
    {
        SalesAccess::staff($request);

        return DB::transaction(function () use ($quote) {
            $quote = SalesQuote::lockForUpdate()->findOrFail($quote->id);

            return ['uuid' => $quote->uuid, 'token' => SalesAccess::renew($quote)];
        });
    }

    public function offer(Request $request, SalesQuote $quote)
    {
        SalesAccess::staff($request);
        $data = $request->validate(['version' => 'required|integer|min:0', 'items' => 'required|array|min:1|max:100', 'items.*.variant_id' => 'required|integer',
            'items.*.quantity' => 'required|integer|min:1|max:100000', 'items.*.price' => 'required|numeric|min:0|max:999999.99', 'shipping_method_id' => 'required|integer',
            'shipping_price' => 'required|numeric|min:0|max:999999.99', 'terms' => 'nullable|string|max:10000', 'valid_until' => 'required|date_format:Y-m-d|after_or_equal:today']);

        return DB::transaction(function () use ($quote, $data, $request) {
            $quote = SalesQuote::lockForUpdate()->findOrFail($quote->id);
            abort_if($quote->order_id || $quote->status === 'withdrawn' || $quote->version != $data['version'], 409, 'Ponuka sa zmenila alebo je uzavretá. Obnovte detail.');
            $items = [];
            foreach ($data['items'] as $item) {
                $v = ProductVariant::with('product')->find($item['variant_id']);
                if (! $v || ! $v->product) {
                    throw ValidationException::withMessages(['items' => 'Variant neexistuje.']);
                }
                if ($item['quantity'] < max(1, (int) $v->min_order)) {
                    throw ValidationException::withMessages(['items' => 'Množstvo je pod minimálnym odberom.']);
                }
                if (round($item['quantity'] * round($item['price'], 2), 2) > 999999.99) {
                    throw ValidationException::withMessages(['items' => 'Suma jednej položky nesmie presiahnuť 999 999,99 €.']);
                }
                $items[] = ['product_id' => $v->product_id, 'variant_id' => $v->id, 'quantity' => $item['quantity'], 'price' => round($item['price'], 2),
                    'snapshot' => ['name' => $v->product->name, 'code' => $v->product->code, 'unit_value' => $v->product->unit_value, 'vat' => $v->product->vat, 'variant_name' => $v->name, 'variant_code' => $v->code]];
            }
            $method = ShippingMethod::where('active', true)->find($data['shipping_method_id']);
            if (! $method) {
                throw ValidationException::withMessages(['shipping_method_id' => 'Vyberte aktívnu dopravu.']);
            }
            $quote->versions()->create(['version' => $quote->version + 1, 'items' => $items, 'shipping' => ['id' => $method->id, 'name' => $method->name, 'price' => round($data['shipping_price'], 2)],
                'terms' => $data['terms'] ?? null, 'valid_until' => $data['valid_until'], 'created_by' => $request->user()->id]);
            $quote->update(['version' => $quote->version + 1, 'status' => 'offered']);

            return $this->detail($quote);
        });
    }

    public function withdraw(Request $request, SalesQuote $quote)
    {
        SalesAccess::staff($request);

        return DB::transaction(function () use ($quote) {
            $quote = SalesQuote::lockForUpdate()->findOrFail($quote->id);
            abort_if($quote->order_id, 409);
            $quote->update(['status' => 'withdrawn']);

            return $this->detail($quote);
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
            $version = $quote->versions()->firstOrFail();
            abort_unless($quote->status === 'offered' && $version->valid_until->endOfDay()->isFuture(), 409, 'Ponuka už nie je platná.');
            // Katalógové ceny sa môžu zmeniť; záväzný je uložený odtlačok prijatej ponuky.
            foreach ($version->items as $item) {
                abort_unless(ProductVariant::whereKey($item['variant_id'])->where('product_id', $item['product_id'])->whereHas('product')->exists(), 409, 'Položka bola vyradená. Vyžiadajte novú ponuku.');
            }
            abort_unless(ShippingMethod::whereKey($version->shipping['id'])->where('active', true)->exists(), 409, 'Doprava bola vyradená. Vyžiadajte novú ponuku.');
            [$customer,$contact] = app(CustomerService::class)->handleCheckout($quote->customer);
            $actor = User::find($quote->user_id);
            $order = Order::create(['customer_id' => $customer->id, 'user_id' => $actor?->isActive() ? $actor->id : $contact?->id,
                'billing_snapshot' => $quote->customer, 'name' => $quote->customer['name'], 'email' => $quote->customer['email'], 'phone' => $quote->customer['phone'],
                'shipping_method_id' => $version->shipping['id'], 'shipping_method_name' => $version->shipping['name'], 'shipping_price' => $version->shipping['price'],
                'note' => 'Ponuka '.$quote->id.', verzia '.$quote->version."\n".($version->terms ?? '')]);
            $order->update(['serial_number' => app(OrderNumberService::class)->next(now()->format('Y-m'))]);
            foreach ($version->items as $item) {
                $order->orderProducts()->create(['product_id' => $item['product_id'], 'product_variant_id' => $item['variant_id'],
                    'product_snapshot' => $item['snapshot'], 'variant_label' => $item['snapshot']['variant_name'], 'quantity' => $item['quantity'], 'price' => $item['price'], 'total' => round($item['quantity'] * $item['price'], 2)]);
            }
            $quote->update(['order_id' => $order->id, 'status' => 'accepted', 'accepted_at' => now(), 'accepted_by' => $data['name']]);
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

        return SalesAccess::download($file->disk,$file->path,$file->name);
    }
}
