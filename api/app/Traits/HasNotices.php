<?php

namespace App\Traits;

use App\Models\Notice;


trait HasNotices {

    public function notices()
    {
        return $this->morphMany(Notice::class, 'fileable');
    }
}