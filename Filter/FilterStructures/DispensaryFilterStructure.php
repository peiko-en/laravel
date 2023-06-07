<?php

declare(strict_types=1);

namespace App\Services\Filter\FilterStructures;

use App\Enums\FilterType;
use App\Models\DispensaryFilter;
use App\Models\Managers\DispensaryFilterManager;
use App\Services\Filter\FilterFactory;

class DispensaryFilterStructure implements FilterStructure
{
    private DispensaryFilterManager $filterManager;
    private FilterFactory $filterFactory;
    private array $filters = [];

    public function __construct(DispensaryFilterManager $filterManager, FilterFactory $filterFactory)
    {
        $this->filterManager = $filterManager;
        $this->filterFactory = $filterFactory;
    }

    public function build(): array
    {
        foreach ($this->fetchStructuredList() as $group => $filter) {
            $this->filters[$group] = $this->filterFactory
                ->getInstance($group)
                ->build($filter);
        }

        return $this->filters;
    }

    private function fetchStructuredList(): array
    {
        $filters = $this->filterManager
            ->all()
            ->groupBy('parent_id');

        return collect($filters[0])->map(function(DispensaryFilter $parent) use ($filters) {
            $parent->setAttribute('kind', FilterType::CHECKBOX->value);
            $parent->setRelation('items', $filters[$parent['id']]);

            return $parent;
        })
        ->pluck(null, 'alias')
        ->map(function($group) {
            $data = $group->toArray();
            $data['hide_all'] = false;
            $data['folding'] = true;
            return $data;
        })
        ->toArray();
    }
}
