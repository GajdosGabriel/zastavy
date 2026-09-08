<?php

namespace App\Http\Controllers\Api\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Http\Requests\CustomerAddressRequest;
use App\Http\Resources\CustomerAddressResource;
use App\Models\Customer;
use App\Models\CustomerAddress;
use App\Services\Delivery\DeliveryAddressService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

/**
 * Adresár doručovacích adries zákazníka.
 *
 * Zmena riadku sa nedotkne už zadaných objednávok — tie majú vlastný odtlačok
 * adresy. Zmazanie tiež nie: objednávka na adresu odkazuje len stopou pôvodu.
 */
class CustomerAddressController extends Controller
{
    public function index(Customer $customer)
    {
        Gate::authorize('view', $customer);

        return CustomerAddressResource::collection($customer->addresses);
    }

    public function store(Customer $customer, CustomerAddressRequest $request)
    {
        Gate::authorize('update', $customer);

        $address = DB::transaction(function () use ($customer, $request) {
            $address = $customer->addresses()->create($this->values($request));

            $this->syncDefault($customer, $address, $request->boolean('is_default'));

            return $address;
        });

        return new CustomerAddressResource($address->refresh());
    }

    public function update(Customer $customer, CustomerAddress $address, CustomerAddressRequest $request)
    {
        Gate::authorize('update', $customer);
        $this->assertOwnedBy($customer, $address);

        DB::transaction(function () use ($customer, $address, $request) {
            $address->update($this->values($request));

            $this->syncDefault($customer, $address, $request->boolean('is_default'));
        });

        return new CustomerAddressResource($address->refresh());
    }

    public function destroy(Customer $customer, CustomerAddress $address)
    {
        Gate::authorize('update', $customer);
        $this->assertOwnedBy($customer, $address);

        $address->delete();

        return response()->noContent();
    }

    /** Očistené hodnoty — rovnaká normalizácia ako pri objednávke. */
    private function values(CustomerAddressRequest $request): array
    {
        $normalized = app(DeliveryAddressService::class)->normalize($request->validated());

        return $normalized + [
            'label' => $request->input('label') ? trim($request->input('label')) : null,
        ];
    }

    /**
     * Predvolená adresa je najviac jedna. Odškrtnutie poslednej predvolenej
     * necháme tak — zákazník bez predvolenej adresy je legitímny stav.
     */
    private function syncDefault(Customer $customer, CustomerAddress $address, bool $isDefault): void
    {
        $address->forceFill(['is_default' => $isDefault])->save();

        if ($isDefault) {
            $customer->addresses()->whereKeyNot($address->id)->update(['is_default' => false]);
        }
    }

    private function assertOwnedBy(Customer $customer, CustomerAddress $address): void
    {
        abort_unless($address->customer_id === $customer->id, 404);
    }
}
