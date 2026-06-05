<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Mark extends Model
{
    use HasFactory;

    protected $guarded = [];

    public function fileable()
    {
        return $this->morphTo();
    }
}
