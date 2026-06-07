<?php

namespace App\Rules;

use Illuminate\Contracts\Validation\Rule;
use Illuminate\Support\Facades\DB;

class IcoRule implements Rule
{
    public function __construct()
    {
        //
    }

    public function passes($attribute, $value)
    {
        if ($value) {
            return ! DB::table('customers')->where('ico', '=', $value)->first();
        }

        return true;
    }

    public function message()
    {
        return __('rules.ico.unique');
    }
}
