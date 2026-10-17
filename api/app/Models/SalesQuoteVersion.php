<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SalesQuoteVersion extends Model
{
    protected $guarded = [];

    protected $casts = ['items' => 'array', 'shipping' => 'array', 'totals' => 'array', 'valid_until' => 'date:Y-m-d', 'sent_at' => 'datetime', 'discount_percent' => 'float'];
}
