<?php

namespace App\Models;

use App\Enums\ModelStatus;
use App\Traits\HasModelStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Announcement extends Model
{
    use HasModelStatus, SoftDeletes;

    protected $guarded = [];

    protected $casts = [
        'status' => ModelStatus::class,
        'published_from' => 'datetime',
        'published_until' => 'datetime',
    ];

    public function scopeActiveForPublic(Builder $query): Builder
    {
        return $query
            ->where('status', ModelStatus::Active->value)
            ->where(function (Builder $query) {
                $query->whereNull('published_from')
                    ->orWhere('published_from', '<=', now());
            })
            ->where(function (Builder $query) {
                $query->whereNull('published_until')
                    ->orWhere('published_until', '>=', now());
            });
    }
}
