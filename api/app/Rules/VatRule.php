<?php

namespace App\Rules;

use Illuminate\Contracts\Validation\Rule;

class VatRule implements Rule
{
    public function __construct()
    {
        //
    }

    public function passes($attribute, $value)
    {
        return in_array($value, [23, 20, 10, 0]);
    }

    public function message()
    {
        return __('rules.vat.exists');
    }
}
