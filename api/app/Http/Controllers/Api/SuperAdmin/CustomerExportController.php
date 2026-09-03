<?php

namespace App\Http\Controllers\Api\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\Order;
use App\Models\OrderProduct;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Export zákazníkov do CSV — stĺpce si volí používateľ, aby sa dal ten istý
 * export použiť na rozposielanie e-mailov aj ako podklad do účtovníctva.
 */
class CustomerExportController extends Controller
{
    private const ATTRIBUTES = [
        'id'                => 'ID',
        'name'              => 'Meno',
        'company'           => 'Firma',
        'email'             => 'E-mail',
        'phone'             => 'Telefón',
        'street'            => 'Ulica',
        'postcode'          => 'PSČ',
        'city'              => 'Mesto',
        'ico'               => 'IČO',
        'dic'               => 'DIČ',
        'ic_dic'            => 'IČ DPH',
        'status'            => 'Status',
        'note'              => 'Poznámka',
        'orders_count'      => 'Počet objednávok',
        'orders_total'      => 'Objednané spolu (€)',
        'last_order_at'     => 'Posledná objednávka',
        'last_login'        => 'Posledné prihlásenie',
        'email_verified_at' => 'E-mail overený',
        'created_at'        => 'Vytvorený',
    ];

    public function attributes()
    {
        Gate::authorize('viewAny', Customer::class);

        return response()->json([
            'data' => collect(self::ATTRIBUTES)
                ->map(fn (string $label, string $key) => ['value' => $key, 'label' => $label])
                ->values(),
        ]);
    }

    public function export(Request $request)
    {
        Gate::authorize('viewAny', Customer::class);

        $validated = $request->validate([
            'attributes'      => ['required', 'array', 'min:1'],
            'attributes.*'    => ['string', 'in:' . implode(',', array_keys(self::ATTRIBUTES))],
            'only_with_email' => ['sometimes', 'boolean'],
        ], [
            'attributes.required' => 'Vyberte aspoň jeden stĺpec exportu.',
        ]);

        $attributes = $validated['attributes'];

        // Súhrny počítame poddotazmi, nie cez relácie — vzťah orders() má
        // vlastné zoradenie a v agregáte by len zbytočne prekážalo.
        $customers = Customer::query()
            // Meno kontaktnej osoby je v `users`; bez načítania dopredu by
            // export spravil dotaz na každý riadok.
            ->with('primaryUser')
            ->select('customers.*')
            ->selectSub(
                Order::selectRaw('COUNT(*)')->whereColumn('orders.customer_id', 'customers.id'),
                'orders_count'
            )
            ->selectSub(
                Order::selectRaw('MAX(orders.created_at)')->whereColumn('orders.customer_id', 'customers.id'),
                'last_order_at'
            )
            ->selectSub(
                OrderProduct::selectRaw('COALESCE(SUM(order_products.total), 0)')
                    ->join('orders', 'orders.id', '=', 'order_products.order_id')
                    ->whereColumn('orders.customer_id', 'customers.id'),
                'orders_total'
            )
            // Pri rozposielaní e-mailov sú riadky bez adresy len šum.
            ->when(
                $request->boolean('only_with_email'),
                fn ($query) => $query->whereNotNull('customers.email')->where('customers.email', '!=', '')
            )
            ->orderBy('customers.id')
            ->get();

        $filename = 'zakaznici_' . now()->format('Y-m-d_His') . '.csv';

        $callback = function () use ($customers, $attributes) {
            $handle = fopen('php://output', 'w');
            // BOM, inak Excel na Windows rozsype diakritiku.
            fwrite($handle, "\xEF\xBB\xBF");

            fputcsv($handle, array_map(fn ($key) => self::ATTRIBUTES[$key], $attributes), ';');

            foreach ($customers as $customer) {
                fputcsv($handle, array_map(fn ($key) => $this->resolveValue($customer, $key), $attributes), ';');
            }

            fclose($handle);
        };

        return new StreamedResponse($callback, 200, [
            'Content-Type'        => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ]);
    }

    private function resolveValue(Customer $customer, string $key): string
    {
        return match ($key) {
            'id'                => (string) $customer->id,
            'name'              => (string) $customer->name,
            'company'           => (string) $customer->company,
            'email'             => (string) $customer->email,
            'phone'             => (string) $customer->phone,
            'street'            => (string) $customer->street,
            'postcode'          => (string) $customer->postcode,
            'city'              => (string) $customer->city,
            'ico'               => (string) $customer->ico,
            'dic'               => (string) $customer->dic,
            'ic_dic'            => (string) $customer->ic_dic,
            'status'            => (string) ($customer->status?->value ?? ''),
            'note'              => (string) $customer->note,
            'orders_count'      => (string) ($customer->orders_count ?? 0),
            // Desatinná čiarka — slovenský Excel inak číslo berie ako text.
            'orders_total'      => number_format((float) ($customer->orders_total ?? 0), 2, ',', ''),
            'last_order_at'     => $this->date($customer->last_order_at),
            'last_login'        => $this->date($customer->last_login),
            'email_verified_at' => $this->date($customer->email_verified_at),
            // created_at má na modeli vlastný cast, ktorý vracia rovno reťazec.
            'created_at'        => (string) $customer->created_at,
            default             => '',
        };
    }

    private function date($value): string
    {
        if (! $value) {
            return '';
        }

        try {
            return Carbon::parse($value)->format('Y-m-d H:i');
        } catch (\Throwable) {
            return (string) $value;
        }
    }
}
