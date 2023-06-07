<?php

declare(strict_types=1);

namespace App\Services\Filter\FilterStructures;

use App\Models\Managers\ProductFilterManager;
use App\Services\Filter\FilterFactory;

class BrandFilterStructure implements FilterStructure
{
    private ProductFilterManager $filterManager;
    private FilterFactory $filterFactory;
    private array $filters = [];

    public function __construct(ProductFilterManager $filterManager, FilterFactory $filterFactory)
    {
        $this->filterManager = $filterManager;
        $this->filterFactory = $filterFactory;
    }

    public function build(): array
    {
        foreach ($this->getStructuredList() as $group => $filter) {
            $this->filters[$group] = $this->filterFactory
                ->getInstance($group)
                ->build($filter);
        }

        return $this->filters;
    }

    private function getStructuredList(): array
    {
        $filters = [];

        if ($cat = $this->filterManager->findByAlias('cat')) {
            $cat->alias = 'bcat';
            $filters[$cat->alias] = $cat->toArray();
        }

        $filters['rating'] = [
            'id' => 1,
            'name' => 'Customer Reviews',
            'alias' => 'rating',
            'kind' => 'rating',
            'hide_all' => 0,
            'folding' => 1,
            'stint_value' => 100,
        ];

        return $filters;
    }
}
