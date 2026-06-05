<?php

namespace App\Http\Controllers\Api;


use App\Models\Customer;
use App\Actions\StoreCheckout;
use App\Http\Requests\OrderRequest;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Http;


class CheckoutController extends Controller
{

    public function show($ico)
    {
        $ico = preg_replace('/\D+/', '', $ico);

        if (strlen($ico) < 6) {
            return response()->json([
                'message' => 'Zadajte platné IČO.',
            ], 422);
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

        $company = $this->findCompanyByIco($ico);

        if (!$company) {
            return response()->json([
                'message' => 'Firmu podľa zadaného IČO sa nepodarilo nájsť.',
            ], 404);
        }

        return response()->json([
            'data' => $company,
            'source' => 'internet',
        ]);
    }

    public function store(OrderRequest $request)
    {

        new StoreCheckout($request);
    }

    private function findCompanyByIco(string $ico): ?array
    {
        $response = Http::acceptJson()
            ->timeout(8)
            ->get("https://api.orsf.sk/v1/companies/{$ico}");

        if (!$response->successful()) {
            return null;
        }

        return $this->orsfToCheckoutData($response->json(), $ico);
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
        return [
            'id' => $customer->id,
            'name' => $customer->name,
            'company' => $customer->company,
            'street' => $customer->street,
            'city' => $customer->city,
            'postcode' => $customer->postcode,
            'ico' => $customer->ico,
            'dic' => $customer->dic,
            'ic_dic' => $customer->ic_dic,
            'email' => $customer->email,
            'phone' => $customer->phone,
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

    private function orsfToCheckoutData(array $company, string $ico): array
    {
        $address = $this->firstFilled([
            data_get($company, 'address'),
            data_get($company, 'seat'),
            data_get($company, 'sidlo'),
            data_get($company, 'registeredAddress'),
        ], []);

        return [
            'name' => '',
            'company' => $this->firstFilled([
                data_get($company, 'name'),
                data_get($company, 'businessName'),
                data_get($company, 'obchodneMeno'),
                data_get($company, 'nazov'),
            ]),
            'street' => $this->formatStreet($company, is_array($address) ? $address : []),
            'city' => $this->firstFilled([
                data_get($address, 'city'),
                data_get($address, 'municipality'),
                data_get($address, 'obec'),
                data_get($company, 'city'),
                data_get($company, 'municipality'),
                data_get($company, 'obec'),
            ]),
            'postcode' => $this->firstFilled([
                data_get($address, 'postalCode'),
                data_get($address, 'psc'),
                data_get($company, 'postalCode'),
                data_get($company, 'psc'),
            ]),
            'ico' => $this->firstFilled([
                data_get($company, 'nationalId'),
                data_get($company, 'ico'),
                $ico,
            ]),
            'dic' => $this->firstFilled([
                data_get($company, 'taxId'),
                data_get($company, 'dic'),
            ]),
            'ic_dic' => $this->firstFilled([
                data_get($company, 'vatId'),
                data_get($company, 'icdph'),
                data_get($company, 'ic_dph'),
            ]),
        ];
    }

    private function formatStreet(array $company, array $address): string
    {
        $street = $this->firstFilled([
            data_get($address, 'street'),
            data_get($address, 'streetName'),
            data_get($address, 'ulica'),
            data_get($company, 'street'),
            data_get($company, 'ulica'),
        ]);

        $number = $this->firstFilled([
            data_get($address, 'streetNumber'),
            data_get($address, 'addressNumber'),
            data_get($address, 'buildingNumber'),
            trim(implode('/', array_filter([
                data_get($address, 'registerNumber'),
                data_get($address, 'orientationNumber'),
            ]))),
            data_get($address, 'cislo'),
            data_get($company, 'streetNumber'),
            data_get($company, 'cislo'),
        ]);

        return trim(implode(' ', array_filter([$street, $number])));
    }

    private function firstFilled(array $values, $default = ''): mixed
    {
        foreach ($values as $value) {
            if ($value !== null && $value !== '') {
                return $value;
            }
        }

        return $default;
    }
}
