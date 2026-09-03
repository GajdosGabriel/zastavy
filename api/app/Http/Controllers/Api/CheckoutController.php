<?php

namespace App\Http\Controllers\Api;


use App\Models\Customer;
use App\Actions\StoreCheckout;
use App\Http\Requests\OrderRequest;
use App\Http\Controllers\Controller;
use App\Services\Companies\CompanyRegistry;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;


class CheckoutController extends Controller
{

    public function show(Request $request, string $ico)
    {
        $ico = preg_replace('/\D+/', '', $ico);

        if (strlen($ico) < 6) {
            return response()->json([
                'message' => 'Zadajte platné IČO.',
            ], 422);
        }

        // Kontaktné údaje zákazníka (meno, e-mail, telefón) sa smú predvyplniť
        // iba prihlásenému internému používateľovi. Verejný e-shop aj portáloví
        // zákazníci dostanú výhradne firemné údaje z verejného registra (GDPR).
        if (! $this->isStaff($request)) {
            return $this->companyFromRegistry($ico);
        }

        $customer = $this->findCustomerByIco($ico);

        if ($customer) {
            $data = $this->customerToCheckoutData($customer);
            $source = 'database';

            if ($this->hasMissingCompanyData($data)) {
                $company = $this->findCompanyByIco($ico);

                if ($company) {
                    $data = $this->fillMissingData($data, $company);
                    $source = 'database_with_internet';
                }
            }

            return response()->json([
                'data' => $data,
                'source' => $source,
            ]);
        }

        return $this->companyFromRegistry($ico);
    }

    private function companyFromRegistry(string $ico)
    {
        $company = $this->findCompanyByIco($ico);

        if (! $company) {
            return response()->json([
                'message' => 'Firmu podľa zadaného IČO sa nepodarilo nájsť.',
            ], 404);
        }

        return response()->json([
            'data' => $company,
            'source' => 'internet',
        ]);
    }

    private function isStaff(Request $request): bool
    {
        return (bool) $request->user('sanctum')
            ?->hasAnyRole(['super-admin', 'admin', 'manager', 'sales', 'warehouse']);
    }

    public function store(OrderRequest $request)
    {
        $order = DB::transaction(function () use ($request) {
            return (new StoreCheckout($request))->getOrder();
        });

        return response()->json([
            'uuid'          => $order->uuid,
            'serial_number' => $order->serial_number,
        ]);
    }

    private function findCompanyByIco(string $ico): ?array
    {
        return app(CompanyRegistry::class)->find($ico);
    }

    private function findCustomerByIco(string $ico): ?Customer
    {
        return Customer::whereIn('ico', array_unique([
            $ico,
            str_pad($ico, 8, '0', STR_PAD_LEFT),
        ]))->first();
    }

    private function customerToCheckoutData(Customer $customer): array
    {
        $contact = $customer->latestUser;

        return [
            'id' => $customer->id,
            'name' => $contact?->username ?? $customer->name,
            'company' => $customer->company,
            'street' => $customer->street,
            'city' => $customer->city,
            'postcode' => $customer->postcode,
            'ico' => $customer->ico,
            'dic' => $customer->dic,
            'ic_dic' => $customer->ic_dic,
            'email' => $contact?->email ?? $customer->email,
            'phone' => $contact?->phone ?? $customer->phone,
        ];
    }

    private function hasMissingCompanyData(array $data): bool
    {
        foreach (['company', 'street', 'city', 'postcode', 'ico', 'dic', 'ic_dic'] as $field) {
            if (!isset($data[$field]) || $data[$field] === '') {
                return true;
            }
        }

        return false;
    }

    private function fillMissingData(array $data, array $fallback): array
    {
        foreach ($fallback as $field => $value) {
            if ((!isset($data[$field]) || $data[$field] === '') && $value !== null && $value !== '') {
                $data[$field] = $value;
            }
        }

        return $data;
    }
}
