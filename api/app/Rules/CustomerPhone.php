<?php

namespace App\Rules;

use App\Services\Customers\CustomerDataRules;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Telefón zákazníka v administrácii — musí mať dĺžku a tvar telefónneho čísla.
 *
 * Používa tú istú normalizáciu ako post-kontrola (CustomerDataRules), takže
 * čo prejde tu, nenájde neskôr ako „nečitateľný telefón".
 */
class CustomerPhone implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $value = trim((string) $value);

        if ($value === '') {
            return;
        }

        if (app(CustomerDataRules::class)->normalizePhone($value) === null) {
            $fail(__('rules.phone.invalid'));
        }
    }
}
