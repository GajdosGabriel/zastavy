<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Správa v konverzácii k cenovej ponuke. `kind` rozlišuje bežnú správu,
 * žiadosť zákazníka o úpravu a odmietnutie ponuky.
 */
class SalesQuoteMessage extends Model
{
    protected $guarded = [];

    protected $casts = ['read_at' => 'datetime'];

    public function quote()
    {
        return $this->belongsTo(SalesQuote::class, 'sales_quote_id');
    }

    public function scopeUnread($query)
    {
        return $query->where('direction', 'customer')->whereNull('read_at');
    }
}
