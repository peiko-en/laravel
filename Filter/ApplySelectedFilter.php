<?php

declare(strict_types=1);

namespace App\Services\Filter;

use Illuminate\Database\Eloquent\Builder;

trait ApplySelectedFilter
{
    protected function applySelectedFilters(Builder $builder): void
    {
        if ($this->selectedFilters) {
            foreach ($this->selectedFilters as $selectedFilter) {
                if ($selectedFilter['group'] == self::SORT_PARAM) {
                    continue;
                }

                app(FilterFactory::class)
                    ->getInstance($selectedFilter['group'])
                    ->apply($builder, $selectedFilter);
            }
        }
    }
}
