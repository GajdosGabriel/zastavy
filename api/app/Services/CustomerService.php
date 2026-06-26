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
            $updateData = $customerData;
            // Preserve existing tax IDs — don't overwrite with null if form field was empty
            foreach (['ico', 'dic', 'ic_dic'] as $field) {
                if (empty($updateData[$field]) && $customer->{$field}) {
                    unset($updateData[$field]);
                }
            }
            $customer->update($updateData);
        } else {
            $customer = Customer::create($customerData);
        }

        $user = $this->storeUser($customer, $request);

        return [$customer, $user];
    }

    public function updateWithUser(Customer $customer, $request): array
    {
        $request = $this->normalizeRequest($request);
        $customerData = $this->customerData($request);

        // Preserve existing tax IDs — don't overwrite with null if form field was empty
        foreach (['ico', 'dic', 'ic_dic'] as $field) {
            if (empty($customerData[$field]) && $customer->{$field}) {
                unset($customerData[$field]);
            }
        }

        $customer->update($customerData);
        $user = $this->storeUser($customer, $request);

        return [$customer, $user];
    }

    public function storeUser(Customer $customer, array $request): ?User
    {
        $email = $request['email'] ?? null;

        if (!$email) {
            return null;
        }

        $user = User::withTrashed()->where('email', $email)->first();

        if ($user) {
            return $user;
        }

        $username = $this->contactName($request) ?: $email;
        [$firstName, $lastName] = $this->nameParts($request, $username);

        return User::create([
            'customer_id' => $customer->id,
            'name' => $username,
            'firstName' => $firstName,
            'lastName' => $lastName,
            'username' => $username,
            'slug' => Str::slug($username),
            'phone' => $request['phone'] ?? null,
            'email' => $email,
            'password' => Hash::make(Str::random(32)),
        ]);
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
            'email' => $request['email'] ?? null,
            'phone' => $request['phone'] ?? null,
            'username' => $request['username'] ?? $request['name'] ?? null,
            'street' => $request['street'] ?? null,
            'postcode' => $request['postcode'] ?? null,
            'city' => $request['city'] ?? null,
            'ico' => $request['ico'] ?? null,
            'dic' => $request['dic'] ?? null,
            'ic_dic' => $request['ic_dic'] ?? null,
            'note' => $request['note'] ?? null,
        ];
    }

    private function contactName(array $request): string
    {
        $name = $request['username'] ?? $request['name'] ?? null;

        if ($name) {
            return trim((string) $name);
        }

        return trim(implode(' ', array_filter([
            $request['firstName'] ?? null,
            $request['lastName'] ?? null,
        ])));
    }

    private function nameParts(array $request, ?string $name): array
    {
        $firstName = trim((string) ($request['firstName'] ?? ''));
        $lastName = trim((string) ($request['lastName'] ?? ''));

        if ($firstName !== '' || $lastName !== '') {
            return [
                $firstName ?: 'Kontakt',
                $lastName,
            ];
        }

        return $this->splitName($name);
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
