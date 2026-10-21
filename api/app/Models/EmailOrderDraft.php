<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Objednávka alebo dopyt z e-mailu — mail zákazníka mimo vlákna objednávky
 * a návrh, ktorý z neho pripravila AI (App\Services\EmailOrders\EmailOrderInbox).
 * Vybavený mail má `order_id` alebo `sales_quote_id`.
 */
class EmailOrderDraft extends Model
{
    public const PENDING = 'pending';

    public const DRAFT = 'draft';

    public const CREATED = 'created';

    public const NOT_ORDER = 'not_order';

    public const FAILED = 'failed';

    public const DISMISSED = 'dismissed';

    protected $guarded = [];

    protected $casts = [
        'preview' => 'array',
        'reasons' => 'array',
        'received_at' => 'datetime',
        'auto_created' => 'boolean',
    ];

    public function order()
    {
        return $this->belongsTo(Order::class)->withTrashed();
    }

    public function salesQuote()
    {
        return $this->belongsTo(SalesQuote::class);
    }

    public function attachments()
    {
        return $this->morphMany(Attachment::class, 'attachable');
    }

    /** Návrh, z ktorého ešte môže vzniknúť objednávka. */
    public function isOpen(): bool
    {
        return in_array($this->status, [self::DRAFT, self::NOT_ORDER, self::FAILED], true) && ! $this->order_id && ! $this->sales_quote_id;
    }
}
