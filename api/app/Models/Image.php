<?php

namespace App\Models;

use App\Enums\ModelStatus;
use App\Support\Media;
use App\Traits\HasModelStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Image extends Model
{
    use HasFactory, SoftDeletes, HasModelStatus;

    protected $guarded = [];

    protected $casts = [
        'status' => ModelStatus::class,
    ];

    public function fileable()
    {
        return $this->morphTo();
    }

    /** URL obrázka podľa disku, na ktorom reálne leží (lokál vs. S3). */
    public function getUrlAttribute(): ?string
    {
        return Media::url($this->disk, $this->path);
    }
}
