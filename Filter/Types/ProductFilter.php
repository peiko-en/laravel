<?php

declare(strict_types=1);

namespace App\Services\Filter\Types;

use App\Models\Categories;
use App\Models\Dispensaries;
use App\Services\Filter\ApplySelectedFilter;
use App\Services\Filter\FilterStructures\ProductFilterStructure;
use App\Services\Sorting\Sorting;
use App\Services\Sorting\Types\ProductSorting;
use Illuminate\Database\Eloquent\Builder;

class ProductFilter extends FilterAbstract
{
    use ApplySelectedFilter;

    private ProductFilterStructure $filterStructure;
    private ?Categories $category = null;
    private ?Dispensaries $dispensary = null;
    protected Sorting $activeSort;

    public function __construct(ProductFilterStructure $filterStructure, ProductSorting $sorting)
    {
        $this->filterStructure = $filterStructure;

        $this->sorts = $sorting->getListWithKeys();
        $this->activeSort = current($this->sorts);
    }

    public function setCategory(Categories $category): self
    {
        $this->category = $category;

        return $this;
    }

    public function setDispensary(Dispensaries $dispensary): self
    {
        $this->dispensary = $dispensary;

        return $this;
    }

    public function load(): void
    {
        if ($this->category) {
            $this->filterStructure->setCategory($this->category);
        }

        $this->filters = $this->filterStructure->build();
    }

    public function apply(Builder $builder): Builder
    {
        if ($this->dispensary) {
            $builder->where('products.disp_id', $this->dispensary->id);
        }

        if ($this->category) {
            $builder->join('product_categories AS prod_cat', 'products.id', 'prod_cat.product_id');
            $builder->where('prod_cat.category_id', $this->category->id);
        }

        $this->applySelectedFilters($builder);

        $this->activeSort->apply($builder);

        return $builder;
    }
}
