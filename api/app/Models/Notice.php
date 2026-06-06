<?php

namespace App\Models;

use App\Enums\ModelStatus;
use App\Traits\HasModelStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Notice extends Model
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
}
