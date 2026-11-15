<?php

namespace App\Filters;

use Illuminate\Http\Request;

abstract class Filters {

    protected $request;

    protected $filters;

    protected $builder;

    /** Filtre, ktoré smú dostať pole (ostatné čakajú jednu hodnotu). */
    protected $arrayFilters = [];

    public function __construct(Request $request)
    {
        $this->request = $request;
    }

    public function apply($builder)
    {
        $this->builder = $builder;

        foreach($this->getFilters() as $filter => $value) {
            if (method_exists($this, $filter)) {
                $this->$filter($value);
            }
        }
    }

    public function getFilters()
    {
        // ?bySearchInput[]=x by v LIKE skončilo chybou „Array to string conversion".
        return array_filter(
            $this->request->only($this->filters),
            fn ($value, $filter) => $value && (is_scalar($value) || in_array($filter, $this->arrayFilters, true)),
            ARRAY_FILTER_USE_BOTH,
        );
    }

}