<?php

namespace App\Rules;

use App\Services\Customers\CustomerDataRules;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Tvrdá kontrola daňového čísla vo formulári administrácie.
 *
 * Pravidlá sú tie isté, ktoré po uložení použije post-kontrola
 * (CustomerDataRules) — napísané raz, na jednom mieste. Rozdiel je len
 * v tom, čo sa s nálezom stane: tu uloženie zastaví, tam sa zapíše
 * do posudku.
 *
 * Blokuje sa zámerne LEN v administrácii. V checkoute je za formulárom
 * zákazník, ktorý chce objednať zástavu — odmietnuť mu objednávku pre
 * preklep v IČO by bola horšia chyba než ten preklep. Tam beží rovnaká
 * kontrola nezáväzne (CustomerCheckController) a zvyšok dorieši post-kontrola.
 */
class CustomerTaxId implements ValidationRule
{
    public function __construct(private readonly string $field)
    {
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $value = trim((string) $value);

        if ($value === '') {
            return;
        }

        $rules = app(CustomerDataRules::class);

        match ($this->field) {
            'ico' => $this->validateIco($value, $rules, $fail),
            'dic' => $this->validateDic($value, $fail),
            'ic_dic' => $this->validateIcDic($value, $fail),
            default => null,
        };
    }

    private function validateIco(string $value, CustomerDataRules $rules, Closure $fail): void
    {
        $digits = preg_replace('/\D+/', '', $value) ?? '';

        if ($digits === '' || strlen($digits) > 8) {
            $fail('IČO musí mať najviac 8 číslic.');

            return;
        }

        // Doplnenie núl nie je chyba — obce majú IČO začínajúce nulami
        // a človek ich pri prepise vynechá. Kontrolná číslica sa počíta
        // až z osemmiestneho tvaru.
        if (! $rules->icoChecksumValid(str_pad($digits, 8, '0', STR_PAD_LEFT))) {
            $fail('IČO nesedí na kontrolnú číslicu — skontrolujte, či nie je preklep.');
        }
    }

    private function validateDic(string $value, Closure $fail): void
    {
        $digits = preg_replace('/\D+/', '', $value) ?? '';

        if (strlen($digits) !== 10) {
            $fail('DIČ musí mať 10 číslic.');
        }
    }

    private function validateIcDic(string $value, Closure $fail): void
    {
        if (preg_match('/^SK\d{10}$/i', preg_replace('/\s+/', '', $value) ?? '') !== 1) {
            $fail('IČ DPH musí byť v tvare SK a 10 číslic.');
        }
    }
}
