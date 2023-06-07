<?php

declare(strict_types=1);

namespace App\Services\Filter\FilterStructures;

use App\Models\Categories;
use App\Models\Managers\ProductFilterManager;
use App\Services\Filter\FilterFactory;

class ProductFilterStructure implements FilterStructure
{
    private ProductFilterManager $filterManager;
    private FilterFactory $filterFactory;
    private ?Categories $category = null;

    private array $filters = [];

    public function __construct(ProductFilterManager $filterManager, FilterFactory $filterFactory)
    {
        $this->filterManager = $filterManager;
        $this->filterFactory = $filterFactory;
    }

    public function setCategory(Categories $category): self
    {
        $this->category = $category;

        return $this;
    }

    public function build(): array
    {
        foreach ($this->fetchStructuredList() as $group => $filter) {
            $this->filters[$group] = $this->filterFactory
                ->getInstance($group)
                ->setCategoryId($this->category->id ?? 0)
                ->build($filter);
        }

        return $this->filters;
    }

    private function fetchStructuredList(): array
    {
        $filters = $this->filterManager->all($category->id ?? 0);

        $groupedFilters = [];

        foreach ($filters as $filter) {
            if ($filter->parent) {
                if (!isset($groupedFilters[$filter->parent->alias])) {
                    $groupedFilters[$filter->parent->alias] = $filter->parent->toArray();
                }

                $groupedFilters[$filter->parent->alias]['items'][] = $filter->toArray();
            } elseif (!isset($groupedFilters[$filter->alias])) {
                $groupedFilters[$filter->alias] = $filter->toArray();
            }
        }

        return $groupedFilters;
    }
}
