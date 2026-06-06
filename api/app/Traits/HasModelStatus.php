<?php

namespace App\Traits;

use App\Enums\ModelStatus;
use Illuminate\Database\Eloquent\Builder;

trait HasModelStatus
{
    public function isArchived(): bool
    {
        return $this->status instanceof ModelStatus
            ? $this->status->isArchived()
            : $this->status === ModelStatus::Archived->value;
    }

    public function archive(): bool
    {
        return $this->forceFill(['status' => ModelStatus::Archived])->save();
    }

    public function activate(): bool
    {
        return $this->forceFill(['status' => ModelStatus::Active])->save();
    }

    public function scopeWithStatus(Builder $query, ModelStatus|string $status): Builder
    {
        return $query->where('status', $status instanceof ModelStatus ? $status->value : $status);
    }

    /**
     * @return array{value: string, label: string, color: string}|null
     */
    public function statusData(): ?array
    {
        if ($this->status instanceof ModelStatus) {
            return $this->status->toArray();
        }

        return ModelStatus::tryFrom((string) $this->status)?->toArray();
    }
}
