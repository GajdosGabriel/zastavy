<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Jedna položka konverzácie k objednávke — správa obsluhy alebo zákazníka,
 * prípadne udalosť grafiky (odoslaný návrh, pripomienky, schválenie).
 */
class OrderMessage extends Model
{
    protected $guarded = [];

    protected $casts = ['read_at' => 'datetime'];

    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    public function artworkVersion()
    {
        return $this->belongsTo(ArtworkVersion::class);
    }

    public function attachments()
    {
        return $this->morphMany(Attachment::class, 'attachable');
    }

    /** Správy zákazníkov, ktoré obsluha ešte nevidela. Rozsah viditeľnosti zodpovedá ProductionController::index. */
    public function scopeUnreadFor($query, User $user)
    {
        return $query->where('direction', 'customer')->whereNull('read_at')->whereHas('order', function ($q) use ($user) {
            if (! $user->hasRole('super-admin')) {
                $q->where(function ($q) use ($user) {
                    $q->where('user_id', $user->id);
                    if ($user->customer_id) {
                        $q->orWhere('customer_id', $user->customer_id);
                    }
                });
            }
        });
    }
}
