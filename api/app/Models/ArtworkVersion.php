<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ArtworkVersion extends Model
{
    protected $guarded = [];

    protected $hidden = ['disk', 'path'];

    protected $casts = ['approved_at' => 'datetime'];

    public function comments()
    {
        return $this->hasMany(ArtworkComment::class);
    }
}
