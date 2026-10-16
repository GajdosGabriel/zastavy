<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * E-mail, ktorý odišiel (alebo neodišiel) zákazníkovi k objednávke.
 * Zapisuje ho SystemLogSubscriber podľa hlavičky X-Order-Ref (App\Support\OrderThread).
 */
class OrderEmail extends Model
{
    public const UPDATED_AT = null;

    public const LABELS = [
        'OrderCreated' => 'Potvrdenie objednávky',
        'OrderUpdated' => 'Zmena objednávky',
        'OrderCancelled' => 'Storno objednávky',
        'OrderPreparing' => 'Príprava zásielky',
        'OrderExpedition' => 'Odoslanie zásielky',
        'OrderDeliveryAddressChanged' => 'Zmena adresy doručenia',
        'OrderReturnProcessed' => 'Vrátenie tovaru',
        'OrderMessageSent' => 'Správa k objednávke',
        'CouponIssued' => 'Zľavový kupón',
    ];

    protected $fillable = ['order_id', 'type', 'subject', 'recipient', 'status', 'error', 'body'];

    protected $hidden = ['body'];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class)->withTrashed();
    }

    public function label(): string
    {
        return self::LABELS[$this->type] ?? 'E-mail k objednávke';
    }
}
