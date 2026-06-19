<?php

namespace App\Traits;

use App\Enums\ModelStatus;
use Illuminate\Database\Eloquent\Builder;

trait HasModelStatus
{
    public function isArchived(): bool
    {
        $status = $this->status;

        if ($status instanceof \BackedEnum) {
            return $status->value === 'archived';
        }

        return $status === 'archived';
    }

    public function archive(): bool
    {
        return $this->forceFill(['status' => 'archived'])->save();
    }

    public function activate(): bool
    {
        return $this->forceFill(['status' => ModelStatus::Active])->save();
    }

    public function scopeWithStatus(Builder $query, \BackedEnum|string $status): Builder
    {
        $value = $status instanceof \BackedEnum ? $status->value : $status;

        return $query->where('status', $value);
    }

    /**
     * @return array{value: string, label: string, color: string}|null
     */
    public function statusData(): ?array
    {
        $status = $this->status;

        if ($status instanceof \BackedEnum && method_exists($status, 'toArray')) {
            return $status->toArray();
        }

        return ModelStatus::tryFrom((string) $status)?->toArray();
    }
}
