<?php

namespace App\Rules;

use Faker\Core\Number;
use Illuminate\Contracts\Validation\Rule;

class VatRule implements Rule
{
    /**
     * Create a new rule instance.
     *
     * @return void
     */
    public function __construct()
    {
        //
    }

    /**
     * Determine if the validation rule passes.
     *
     * @param  string  $attribute
     * @param  mixed  $value
     * @return bool
     */
    public function passes($attribute, $value)
    {
        return in_array($value, [23,20,10,0]);
    }

    /**
     * Get the validation error message.
     *
     * @return string
     */
    public function message()
    {
        return 'DPH tejto hodnoty neexistuje.';
    }
}
