<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SalesQuote extends Model
{
    protected $guarded = [];

    protected $hidden = ['token_hash'];

    protected $casts = ['customer' => 'array', 'requested_items' => 'array', 'token_expires_at' => 'datetime', 'accepted_at' => 'datetime'];

    public function versions()
    {
        return $this->hasMany(SalesQuoteVersion::class)->orderByDesc('version');
    }

    public function attachments()
    {
        return $this->morphMany(Attachment::class, 'attachable');
    }

    public function order()
    {
        return $this->belongsTo(Order::class)->withTrashed();
    }
}
