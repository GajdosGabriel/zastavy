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

    /** Od kedy sa obsah e-mailu už neuchováva (config/logging.php), null = uchováva sa vždy. */
    public static function bodyCutoff(): ?\Illuminate\Support\Carbon
    {
        $days = (int) config('logging.order_email.body_days', 730);

        return $days > 0 ? now()->subDays($days) : null;
    }

    /** Vymaže HTML starých e-mailov; záznam o odoslaní zostáva. Vráti počet riadkov. */
    public static function pruneBodies(): int
    {
        $cutoff = static::bodyCutoff();

        return $cutoff ? static::query()->whereNotNull('body')->where('created_at', '<', $cutoff)->update(['body' => null]) : 0;
    }

    /** Obsah už bol vymazaný podľa doby uchovávania (nie pre veľkosť a pod.). */
    public function bodyExpired(): bool
    {
        $cutoff = static::bodyCutoff();

        return $this->body === null && $cutoff && $this->created_at?->lt($cutoff);
    }

    public function label(): string
    {
        return self::LABELS[$this->type] ?? 'E-mail k objednávke';
    }
}
