<?php

declare(strict_types=1);

namespace App\Services\Filter\FilterGroups;

use Illuminate\Database\Eloquent\Builder;

class BrandCategoryFilter extends CategoryFilter
{
    public function apply(Builder $builder, array $selectedFilter = []): Builder
    {
        if (isset($selectedFilter['values']) && $selectedFilter['values']) {
            $builder->join('products AS prod', 'brands.id', 'prod.brand_id');
            $builder->join('product_categories AS pc', 'prod.id', 'pc.product_id');
            $builder->whereIn('pc.category_id', $selectedFilter['values']);
        }

        return $builder;
    }
}
