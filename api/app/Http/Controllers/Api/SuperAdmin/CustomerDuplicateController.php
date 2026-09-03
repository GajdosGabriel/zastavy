<?php

namespace App\Http\Controllers\Api\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Services\Customers\CustomerDuplicateService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/**
 * Zákazníci, ktorí sú v tabuľke viackrát.
 *
 * Zoznam je len prehľad; zlučuje sa vždy jedným výslovným volaním na
 * konkrétneho zákazníka, ktorý má zostať. Hromadné „zluč všetko" tu
 * zámerne nie je — presun objednávok medzi zákazníkmi sa nemá dať spustiť
 * jedným kliknutím na 339 skupín naraz. Na to je príkaz v konzole, kde je
 * jasné, čo sa deje.
 */
class CustomerDuplicateController extends Controller
{
    public function __construct(private readonly CustomerDuplicateService $service)
    {
    }

    public function index(Request $request)
    {
        Gate::authorize('viewAny', Customer::class);

        $groups = $this->service->groups((int) $request->integer('limit', 50));

        return response()->json([
            'data' => $groups->map(fn (array $group) => [
                'key' => $group['key'],
                'reason' => $group['reason'],
                'customers' => $group['customers']->map(fn (Customer $customer) => [
                    'id' => $customer->id,
                    'company' => $customer->company,
                    'name' => $customer->name,
                    'street' => $customer->street,
                    'city' => $customer->city,
                    'postcode' => $customer->postcode,
                    'ico' => $customer->ico,
                    'dic' => $customer->dic,
                    'email' => $customer->email,
                    'orders' => $customer->orders_count ?? 0,
                    'created_at' => $customer->created_at,
                ])->values(),
            ])->values(),
        ]);
    }

    /**
     * Zlúči vybrané záznamy do `$customer`.
     *
     * Ten, ktorý zostáva, je v adrese — nie v tele požiadavky. Tak sa dá
     * autorizovať tým istým pravidlom ako každá iná úprava zákazníka.
     */
    public function store(Customer $customer, Request $request)
    {
        Gate::authorize('update', $customer);
        Gate::authorize('delete', $customer);

        $validated = $request->validate([
            'merge' => 'required|array|min:1',
            'merge.*' => 'integer|distinct|different:customer',
        ]);

        $result = $this->service->merge($customer, $validated['merge']);

        if ($result['merged'] === []) {
            return response()->json(['message' => 'Nie je čo zlúčiť.'], 422);
        }

        return response()->json([
            'message' => sprintf(
                'Zlúčené %d záznamov, presunutých objednávok %d.',
                count($result['merged']),
                $result['orders'],
            ),
            'data' => $result,
        ]);
    }
}
