<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SalesQuoteVersion extends Model
{
    protected $guarded = [];

    protected $casts = ['items' => 'array', 'shipping' => 'array', 'valid_until' => 'date:Y-m-d'];
}
