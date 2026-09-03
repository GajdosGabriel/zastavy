<?php

namespace App\Services\Companies;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Firma podľa IČO z verejného registra (api.orsf.sk).
 *
 * Vytiahnuté z CheckoutController, lebo tú istú odpoveď potrebuje aj
 * post-kontrola zákazníkov: keď v riadku chýba DIČ, register ho pozná a
 * hádať ho netreba. Dve kópie mapovania odpovede by sa časom rozišli a
 * checkout by dopĺňal iné údaje než kontrola.
 *
 * Odpoveď sa kešuje — pri prechode celej tabuľky sa to isté IČO objaví
 * viackrát (pobočky tej istej obce) a register nie je náš.
 */
class CompanyRegistry
{
    /** Ako dlho veríme odpovedi registra. Názov firmy sa nemení každý deň. */
    private const CACHE_DAYS = 30;

    /**
     * @return array<string, string>|null  null = register firmu nepozná alebo neodpovedal
     */
    public function find(string $ico): ?array
    {
        $ico = preg_replace('/\D+/', '', $ico) ?? '';

        if (strlen($ico) < 6) {
            return null;
        }

        $padded = str_pad($ico, 8, '0', STR_PAD_LEFT);

        return Cache::remember(
            'company_registry:'.$padded,
            now()->addDays(self::CACHE_DAYS),
            fn () => $this->fetch($padded),
        );
    }

    private function fetch(string $ico): ?array
    {
        try {
            $response = Http::acceptJson()->timeout(8)->get("https://api.orsf.sk/v1/companies/{$ico}");
        } catch (\Throwable $e) {
            // Výpadok registra nesmie zhodiť ani checkout, ani kontrolu —
            // obom stačí odpoveď „nevieme".
            Log::warning('Register firiem neodpovedal.', ['ico' => $ico, 'error' => $e->getMessage()]);

            return null;
        }

        if (! $response->successful()) {
            return null;
        }

        return $this->toCheckoutData($response->json(), $ico);
    }

    /**
     * Odpoveď registra do tvaru, v akom údaje drží formulár aj `customers`.
     *
     * Kľúče sa v odpovedi líšia podľa toho, z akého zdroja register subjekt
     * načítal (RPO, ORSR, staršie záznamy), preto sa každý údaj hľadá vo
     * viacerých menách a berie sa prvý vyplnený.
     */
    public function toCheckoutData(array $company, string $ico): array
    {
        $address = $this->firstFilled([
            data_get($company, 'address'),
            data_get($company, 'seat'),
            data_get($company, 'sidlo'),
            data_get($company, 'registeredAddress'),
        ], []);

        $address = is_array($address) ? $address : [];

        return [
            'name' => '',
            'company' => $this->firstFilled([
                data_get($company, 'name'),
                data_get($company, 'businessName'),
                data_get($company, 'obchodneMeno'),
                data_get($company, 'nazov'),
            ]),
            'street' => $this->formatStreet($company, $address),
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

    private function firstFilled(array $values, mixed $default = ''): mixed
    {
        foreach ($values as $value) {
            if ($value !== null && $value !== '') {
                return $value;
            }
        }

        return $default;
    }
}
