<?php

namespace App\Filters;

use Illuminate\Http\Request;
use Illuminate\Database\Eloquent\Builder;

class ProductFilter extends Filters
{

    protected $filters = ['bySearchInput', 'isUnpublished', 'byCategory', 'isDeleted'];

    public function bySearchInput($value)
    {
        return $this->builder
            ->where('code', 'like', '%' . $value . '%')
            ->orWhere('name', 'like', '%' . $value . '%')
            ->orWhere('description', 'like', '%' . $value . '%');
    }

    public function isUnpublished($value)
    {
        return $this->builder->where('published', $value ? 0 : $value );
    }

    public function isDeleted()
    {
        return $this->builder->onlyTrashed();
    }

    public function byCategory($id)
    {
        return $this->builder->whereHas('categories', function ($query) use ($id) {
            $query->whereId($id);
        })->get();
    }
}
