<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class OrderReturn extends Model
{
    use HasFactory, SoftDeletes;

    protected $guarded = [];

    protected $casts = [
        'processed_at' => 'datetime',
    ];

    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    public function items()
    {
        return $this->hasMany(OrderReturnItem::class);
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function processedBy()
    {
        return $this->belongsTo(User::class, 'processed_by');
    }

    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    public function isProcessed(): bool
    {
        return $this->status === 'processed';
    }

    public static function reasonLabels(): array
    {
        return [
            'not_accepted' => 'Neprevzatá zásielka',
            'damaged'      => 'Poškodený tovar',
            'wrong_item'   => 'Nesprávny tovar',
            'other'        => 'Iný dôvod',
        ];
    }

    public function getReasonLabelAttribute(): string
    {
        return self::reasonLabels()[$this->reason] ?? $this->reason;
    }
}
