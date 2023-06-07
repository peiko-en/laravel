<?php

declare(strict_types=1);

namespace App\Services\Filter\Types;

use App\Services\Filter\FilterStructures\DispensaryFilterStructure;
use App\Services\Filter\FilterStructures\FilterStructure;
use App\Services\Sorting\Types\DispensarySorting;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\JoinClause;

class DispensaryFilter extends FilterAbstract
{
    private FilterStructure $filterStructure;

    public function __construct(DispensaryFilterStructure $filterStructure, DispensarySorting $sorting)
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
        if ($this->selectedFilters) {
            foreach ($this->selectedFilters as $i => $selectedFilter) {
                if ($selectedFilter['group'] == self::SORT_PARAM) {
                    continue;
                }

                $prefix = 'afs' . $i;
                $builder->join('dispensary_filter_assign AS ' . $prefix, function(JoinClause $join) use ($selectedFilter, $prefix) {
                    $join->on('dispensaries.id', '=', $prefix . '.dispensary_id');
                    $join->whereIn($prefix . '.filter_item_id', $selectedFilter['values']);
                });
            }
        }

        $this->activeSort->apply($builder);

        return $builder;
    }
}
