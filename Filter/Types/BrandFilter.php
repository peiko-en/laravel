<?php

declare(strict_types=1);

namespace App\Services\Filter\Types;

use App\Services\Filter\ApplySelectedFilter;
use App\Services\Filter\FilterStructures\BrandFilterStructure;
use App\Services\Sorting\Types\BrandSorting;
use Illuminate\Database\Eloquent\Builder;

class BrandFilter extends FilterAbstract
{
    use ApplySelectedFilter;

    private BrandFilterStructure $filterStructure;

    public function __construct(BrandFilterStructure $filterStructure, BrandSorting $sorting)
    {
        $this->filterStructure = $filterStructure;

        $this->sorts = $sorting->getListWithKeys();
        $this->activeSort = current($this->sorts);
    }

    public function load(): void
    {
        $this->filters = $this->filterStructure->build();
    }

    public function apply(Builder $builder): Builder
    {
        $this->applySelectedFilters($builder);
        $this->activeSort->apply($builder);

        return $builder;
    }
}
