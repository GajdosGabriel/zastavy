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

    public function handleCheckout($request, ?User $actor = null): array
    {
        $request = $this->normalizeRequest($request);
        // Anonymný nákup nesmie meniť existujúcu firmu ani jej členstvá.
        if (! $actor?->isStaff()) {
            // Opakovaná objednávka toho istého kontaktu sa viaže na už existujúceho
            // zákazníka (podľa IČO + e-mailu), údaje firmy sa pritom nemenia.
            $customer = $this->findReturningCustomer($request)
                ?? Customer::create($this->customerData($request));
            $contact = $this->storeUser($customer, $request);

            return [$customer, $actor ?? $contact];
        }

        $customer = $this->findCustomer($request);
        $customerData = $this->customerData($request);

        if ($customer && ! $actor->can('update', $customer)) {
            return [$customer, null];
        }

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
        $user = $this->writeContact($customer, $request);

        // Meno zákazníka je accessor nad `users`. Pri zakladaní nového
        // zákazníka stihne posudok (observer na uložení) prečítať `name` ešte
        // predtým, než kontakt existuje, a relácia sa načíta prázdna. Bez
        // tohto by volajúci dostal zákazníka bez mena, hoci kontakt už má.
        $customer->unsetRelation('primaryUser')->unsetRelation('latestUser');

        return $user;
    }

    private function writeContact(Customer $customer, array $request): ?User
    {
        $email = $request['email'] ?? null;

        if (!$email) {
            return null;
        }

        $username = $this->contactName($request) ?: $email;
        [$firstName, $lastName] = $this->nameParts($request, $username);
        $phone = $request['phone'] ?? null;

        // Kontaktná osoba sa hľadá iba v rámci firmy. E-mail nie je globálny
        // identifikátor zákazníka — tá istá adresa môže patriť kontaktu inej
        // firmy a recyklovaním cudzieho záznamu by objednávka skončila
        // priradená človeku z inej organizácie. Ďalšia kontaktná osoba tej
        // istej firmy tak dostane vlastný záznam v users.
        $user = User::withTrashed()
            ->where('customer_id', $customer->id)
            ->where('email', $email)
            ->orderBy('id')
            ->first();

        if ($user) {
            // Kontakt objednáva znova po zmazaní — bez obnovenia by objednávka
            // visela na soft-deleted používateľovi a v zoznamoch by chýbal.
            if ($user->trashed()) {
                $user->restore();
            }

            // Prepisujeme len tým, čo z formulára naozaj prišlo — prázdne pole
            // nesmie vymazať už uložené meno či telefón.
            $user->fill(array_filter([
                'name' => $username,
                'firstName' => $firstName,
                'lastName' => $lastName,
                'username' => $username,
                'slug' => Str::slug($username),
                'phone' => $phone,
            ], fn ($value) => $value !== null && $value !== ''));

            if ($user->isDirty()) {
                $user->save();
            }

            return $user;
        }

        return User::create([
            'customer_id' => $customer->id,
            'name' => $username,
            'firstName' => $firstName,
            'lastName' => $lastName,
            'username' => $username,
            'slug' => Str::slug($username),
            'phone' => $phone,
            'email' => $email,
            'password' => Hash::make(Str::random(32)),
        ]);
    }

    /**
     * Anonymný nákup môže znovupoužiť zákazníka len ak e-mail už patrí jeho
     * kontaktu. Samotné IČO je verejné — cudzia osoba by sa inak pripojila
     * k cudzej firme jej zadaním.
     */
    private function findReturningCustomer(array $request): ?Customer
    {
        $ico = preg_replace('/\s+/', '', (string) ($request['ico'] ?? ''));
        $email = $request['email'] ?? null;

        if ($ico === '' || ! $email) {
            return null;
        }

        // Stĺpec drží IČO doplnené nulami na 8 miest (IcoFormater).
        $ico = str_pad($ico, 8, '0', STR_PAD_LEFT);

        return Customer::where('ico', $ico)
            ->where(function ($query) use ($email) {
                $query->where('email', $email)
                    ->orWhereHas('users', fn ($users) => $users->where('email', $email));
            })
            ->orderBy('id')
            ->first();
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

        // Meno kontaktnej osoby tu zámerne nie je — patrí do `users` a zapisuje
        // ho storeUser(). Na `customers` bývalo v dvoch stĺpcoch naraz
        // (`name`, `username`) a formulár aj tak ukazoval hodnotu z `users`.
        // Poznámka sa prepisuje len keď ju formulár naozaj poslal — checkout ju
        // nepozná a jeho uloženie by ju inak zakaždým vymazalo.
        $note = array_key_exists('note', $request) ? ['note' => $request['note'] ?: null] : [];

        return $note + [
            'company' => $company,
            'email' => $request['email'] ?? null,
            'phone' => $request['phone'] ?? null,
            'street' => $request['street'] ?? null,
            // NOT NULL stĺpce — chýbajúca hodnota je prázdny reťazec, nie SQL chyba.
            'postcode' => $request['postcode'] ?? '',
            'city' => $request['city'] ?? '',
            'ico' => $request['ico'] ?? null,
            'dic' => $request['dic'] ?? null,
            'ic_dic' => $request['ic_dic'] ?? null,
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
