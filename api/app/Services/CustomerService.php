<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class CustomerService
{
    public function handle($request)
    {
        $request = $this->normalizeRequest($request);

        return Customer::create($this->customerData($request));
    }

    public function handleCheckout($request): array
    {
        $request = $this->normalizeRequest($request);
        $customer = $this->findCustomer($request);
        $customerData = $this->customerData($request);

        if ($customer) {
            $customer->update($customerData);
        } else {
            $customer = Customer::create($customerData);
        }

        $user = $this->storeUser($customer, $request);

        return [$customer, $user];
    }

    public function updateWithUser(Customer $customer, $request): array
    {
        $request = $this->normalizeRequest($request);
        $customer->update($this->customerData($request));
        $user = $this->storeUser($customer, $request);

        return [$customer, $user];
    }

    public function storeUser(Customer $customer, array $request): ?User
    {
        $email = $request['email'] ?? null;

        if (!$email) {
            return null;
        }

        $username = $request['username'] ?? $request['name'] ?? null;
        [$firstName, $lastName] = $this->splitName($username);

        return User::updateOrCreate(
            [
                'customer_id' => $customer->id,
                'email' => $email,
            ],
            [
                'firstName' => $firstName,
                'lastName' => $lastName,
                'username' => $username,
                'slug' => Str::slug($username ?: $email),
                'phone' => $request['phone'] ?? null,
                'password' => Hash::make(Str::random(32)),
            ]
        );
    }

    private function findCustomer(array $request): ?Customer
    {
        if (!empty($request['id'])) {
            return Customer::find($request['id']);
        }

        if (!empty($request['ico'])) {
            return Customer::where('ico', $request['ico'])->first();
        }

        return null;
    }

    private function customerData(array $request): array
    {
        $company = $request['company'] ?? null;

        return [
            'name' => $company ?: ($request['name'] ?? 'Zákazník'),
            'company' => $company,
            'street' => $request['street'] ?? null,
            'postcode' => $request['postcode'] ?? null,
            'city' => $request['city'] ?? null,
            'ico' => $request['ico'] ?? null,
            'dic' => $request['dic'] ?? null,
            'ic_dic' => $request['ic_dic'] ?? null,
            'note' => $request['note'] ?? null,
        ];
    }

    private function splitName(?string $name): array
    {
        $name = trim((string) $name);

        if ($name === '') {
            return ['Kontakt', ''];
        }

        $parts = preg_split('/\s+/', $name, 2);

        return [
            $parts[0] ?: 'Kontakt',
            $parts[1] ?? '',
        ];
    }

    private function normalizeRequest($request): array
    {
        if (is_array($request)) {
            return $request;
        }

        if (method_exists($request, 'all')) {
            return $request->all();
        }

        return (array) $request;
    }
}
