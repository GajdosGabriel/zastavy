<?php

namespace App\Casts;

use Illuminate\Contracts\Database\Eloquent\CastsAttributes;

class IcoFormater implements CastsAttributes
{
    /**
     * Cast the given value.
     *
     * @param  \Illuminate\Database\Eloquent\Model  $model
     * @param  string  $key
     * @param  mixed  $value
     * @param  array  $attributes
     * @return mixed
     */
    public function get($model, string $key, $value, array $attributes)
    {
        return $value;
    }

    /**
     * Prepare the given value for storage.
     *
     * @param  \Illuminate\Database\Eloquent\Model  $model
     * @param  string  $key
     * @param  mixed  $value
     * @param  array  $attributes
     * @return mixed
     */

    //  V prípade že user zada 6 miestní ico, doplní to do 8 miestného formátu.
    public function set($model, string $key, $value, array $attributes)
    {
        if ($value == null) {
            return NULL;
        }
        return str_pad($value, 8, "0", STR_PAD_LEFT);
    }
}
