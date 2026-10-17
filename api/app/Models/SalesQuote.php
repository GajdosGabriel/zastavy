<?php

namespace App\Models;

use App\Services\SalesAccess;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Database\Eloquent\Model;

class SalesQuote extends Model
{
    protected $guarded = [];

    protected $hidden = ['token_hash', 'token'];

    protected $casts = ['customer' => 'array', 'requested_items' => 'array', 'token_expires_at' => 'datetime', 'accepted_at' => 'datetime', 'token' => 'encrypted',
        'valid_until' => 'date:Y-m-d', 'sent_at' => 'datetime', 'viewed_at' => 'datetime', 'total_net' => 'float', 'total_gross' => 'float'];

    /** Všetky verzie vrátane konceptu (ten má najvyššie číslo a prázdne sent_at). */
    public function versions()
    {
        return $this->hasMany(SalesQuoteVersion::class)->orderByDesc('version');
    }

    /** Verzie, ktoré videl zákazník. */
    public function sentVersions()
    {
        return $this->versions()->whereNotNull('sent_at');
    }

    public function draft()
    {
        return $this->hasOne(SalesQuoteVersion::class)->whereNull('sent_at');
    }

    public function attachments()
    {
        return $this->morphMany(Attachment::class, 'attachable');
    }

    public function order()
    {
        return $this->belongsTo(Order::class)->withTrashed();
    }

    public function customerRecord()
    {
        return $this->belongsTo(Customer::class, 'customer_id')->withTrashed();
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function isClosed(): bool
    {
        return $this->order_id || in_array($this->status, ['accepted', 'withdrawn'], true);
    }

    public function isExpired(): bool
    {
        return $this->status === 'offered' && $this->valid_until?->copy()->endOfDay()->isPast();
    }

    /** Nový odkaz pre zákazníka; všetky doteraz rozoslané prestanú platiť. */
    public function renewToken(): string
    {
        $token = SalesAccess::renew($this);
        $this->forceFill(['token' => $token])->save();

        return $token;
    }

    /** Platný odkaz bez zneplatnenia starších e-mailov (rovnako ako OrderProduction). */
    public function shareToken(): string
    {
        try {
            $token = $this->token;
        } catch (DecryptException) {
            $token = null;
        }
        if (! $token || ! hash_equals((string) $this->token_hash, hash('sha256', $token))) {
            return $this->renewToken();
        }
        $this->forceFill(['token_expires_at' => now()->addDays(90)])->save();

        return $token;
    }

    public function shareUrl(): string
    {
        return rtrim(config('app.frontend_url'), '/').'/ponuka/'.$this->uuid.'#token='.$this->shareToken();
    }

    public function displayName(): string
    {
        return ($this->customer['company'] ?? '') ?: ($this->customer['name'] ?? '');
    }
}
