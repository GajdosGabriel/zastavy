<?php

namespace App\Services\Delivery;

use App\Models\Customer;
use App\Models\CustomerAddress;
use App\Models\Order;
use App\Support\AddressFormatter;

/**
 * Doručovacia adresa objednávky — z čoho vznikne a čo si z nej zákazník nechá.
 *
 * Vstup má tri podoby a všetky tri končia tým istým odtlačkom na objednávke:
 *   • `customer_address_id` — zákazník si vybral z adresára,
 *   • `delivery` s ulicou    — vypísal novú adresu,
 *   • nič                    — doručiť na fakturačnú adresu.
 *
 * Prázdne stĺpce sú teda plnohodnotná odpoveď, nie chyba: objednávka bez
 * odtlačku ide na sídlo zákazníka a takto vyzerá aj celá história spred tejto
 * funkcie.
 */
class DeliveryAddressService
{
    /**
     * Stĺpce `delivery_*` (plus `customer_address_id`) pre uloženie na objednávku.
     *
     * Vracia vždy všetky kľúče, aj keď sú null — pri úprave objednávky sa tým
     * adresa dá aj odobrať, nielen prepísať.
     */
    public function resolve(Customer $customer, ?array $payload, ?int $addressId = null, ?array $billing = null): array
    {
        $address = $addressId ? $this->addressOf($customer, $addressId) : null;

        if ($address) {
            return $address->toSnapshot() + ['customer_address_id' => $address->id];
        }

        $normalized = $this->normalize($payload ?? []);

        if (! $this->isUsable($normalized)) {
            return $this->emptySnapshot();
        }

        // Adresa zhodná s fakturačným odtlačkom nepotrebuje samostatné polia.
        // Pri úprave porovnávame pôvodnú adresu objednávky, nie aktuálny profil.
        // Iný príjemca na tej istej adrese („k rukám p. Novák", iná pobočka
        // pod vlastným menom) odtlačok naopak potrebuje, inak by sa stratil.
        $sameRecipient = blank($normalized['company']) && blank($normalized['name']) && blank($normalized['note']);

        if ($sameRecipient && $this->matchesBilling($normalized, $customer, $billing)) {
            return $this->emptySnapshot();
        }

        return [
            'delivery_company'    => $normalized['company'],
            'delivery_name'       => $normalized['name'],
            'delivery_street'     => $normalized['street'],
            'delivery_postcode'   => $normalized['postcode'],
            'delivery_city'       => $normalized['city'],
            'delivery_country'    => $normalized['country'],
            'delivery_phone'      => $normalized['phone'],
            'delivery_note'       => $normalized['note'],
            'customer_address_id' => null,
        ];
    }

    /**
     * Uloží adresu z objednávky do adresára zákazníka.
     *
     * Rovnakú adresu nezaloží druhýkrát — zákazník, ktorý objednáva na tú istú
     * pobočku štvrťročne, by inak mal v adresári štyri rovnaké riadky.
     */
    public function remember(Customer $customer, array $snapshot, ?string $label = null): ?CustomerAddress
    {
        $values = [
            'company'  => $snapshot['delivery_company'] ?? null,
            'name'     => $snapshot['delivery_name'] ?? null,
            'street'   => $snapshot['delivery_street'] ?? null,
            'postcode' => $snapshot['delivery_postcode'] ?? null,
            'city'     => $snapshot['delivery_city'] ?? null,
            'country'  => $snapshot['delivery_country'] ?? 'SK',
            'phone'    => $snapshot['delivery_phone'] ?? null,
            'note'     => $snapshot['delivery_note'] ?? null,
        ];

        if (blank($values['street']) || blank($values['city'])) {
            return null;
        }

        $fingerprint = AddressFormatter::fingerprint(
            $values['company'],
            $values['street'],
            $values['postcode'],
            $values['city'],
            $values['country'],
        );

        $existing = $customer->addresses()->get()
            ->first(fn (CustomerAddress $address) => $address->fingerprint() === $fingerprint);

        if ($existing) {
            return $existing;
        }

        return $customer->addresses()->create($values + [
            'label' => AddressFormatter::normalizeText($label, 100),
            'is_default' => $customer->addresses()->count() === 0,
        ]);
    }

    /** Očistené hodnoty z formulára — bez medzier v PSČ, bez prázdnych reťazcov. */
    public function normalize(array $payload): array
    {
        return [
            'company'  => AddressFormatter::normalizeText($payload['company'] ?? null, 200),
            'name'     => AddressFormatter::normalizeText($payload['name'] ?? null, 150),
            'street'   => AddressFormatter::normalizeText($payload['street'] ?? null, 250),
            'postcode' => AddressFormatter::normalizePostcode($payload['postcode'] ?? null),
            'city'     => AddressFormatter::normalizeText($payload['city'] ?? null, 100),
            'country'  => strtoupper(AddressFormatter::normalizeText($payload['country'] ?? null, 2) ?? 'SK'),
            'phone'    => AddressFormatter::normalizePhone($payload['phone'] ?? null),
            'note'     => AddressFormatter::normalizeText($payload['note'] ?? null, 255),
        ];
    }

    /** Adresa, ktorá sa dá použiť na obálku: ulica, PSČ a mesto. */
    public function isUsable(array $normalized): bool
    {
        return filled($normalized['street']) && filled($normalized['city']) && filled($normalized['postcode']);
    }

    /** Zmena adresy sa zapisuje aj s tým, kto ju spravil — kvôli neskorším otázkam. */
    public function applyToOrder(Order $order, array $snapshot, string $changedBy): Order
    {
        $order->forceFill($snapshot + [
            'delivery_changed_at' => now(),
            'delivery_changed_by' => AddressFormatter::normalizeText($changedBy, 100),
        ])->save();

        return $order;
    }

    private function matchesBilling(array $normalized, Customer $customer, ?array $snapshot = null): bool
    {
        $billing = AddressFormatter::fingerprint(
            $snapshot['street'] ?? $customer->street,
            AddressFormatter::normalizePostcode($snapshot['postcode'] ?? $customer->getRawOriginal('postcode')),
            $snapshot['city'] ?? $customer->city,
        );

        $delivery = AddressFormatter::fingerprint(
            $normalized['street'],
            $normalized['postcode'],
            $normalized['city'],
        );

        return $billing !== '' && $billing === $delivery;
    }

    private function addressOf(Customer $customer, int $addressId): ?CustomerAddress
    {
        return $customer->addresses()->whereKey($addressId)->first();
    }

    private function emptySnapshot(): array
    {
        return [
            'delivery_company'    => null,
            'delivery_name'       => null,
            'delivery_street'     => null,
            'delivery_postcode'   => null,
            'delivery_city'       => null,
            'delivery_country'    => null,
            'delivery_phone'      => null,
            'delivery_note'       => null,
            'customer_address_id' => null,
        ];
    }
}
