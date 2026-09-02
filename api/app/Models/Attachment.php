<?php

namespace App\Models;

use App\Support\Media;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Attachment extends Model
{
    use HasFactory, SoftDeletes;

    protected $guarded = [];

    protected $casts = [
        'size' => 'integer',
    ];

    public function attachable()
    {
        return $this->morphTo();
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function isImage(): bool
    {
        return is_string($this->mime) && str_starts_with(strtolower($this->mime), 'image/');
    }

    /** Zmaže aj fyzický súbor — prílohy sú viazané na jednu objednávku. */
    public function deleteWithFile(): void
    {
        Media::delete($this->disk, $this->path);
        $this->delete();
    }
}
