<?php

namespace App\Models;

use App\Services\SalesAccess;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Database\Eloquent\Model;

class OrderProduction extends Model
{
    protected $guarded = [];

    protected $hidden = ['token_hash', 'token'];

    protected $casts = ['production_due_at' => 'date:Y-m-d', 'delivery_due_at' => 'date:Y-m-d', 'token_expires_at' => 'datetime', 'token' => 'encrypted', 'planned' => 'boolean'];

    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    /** Nový odkaz pre zákazníka; všetky doteraz rozoslané prestanú platiť. */
    public function renewToken(): string
    {
        $token = SalesAccess::renew($this);
        $this->forceFill(['token' => $token])->save();

        return $token;
    }

    /** Platný odkaz bez zneplatnenia starších e-mailov; každé použitie predĺži platnosť. */
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

    /** Súkromná stránka konverzácie a schvaľovania grafiky. */
    public function shareUrl(): string
    {
        return rtrim(config('app.frontend_url'), '/').'/objednavka/'.$this->order->uuid.'/spravy#token='.$this->shareToken();
    }
}
