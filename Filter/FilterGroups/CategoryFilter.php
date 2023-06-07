<?php

declare(strict_types=1);

namespace App\Services\Filter\FilterGroups;

use App\Models\Categories;
use App\Models\Managers\CategoryManager;
use Illuminate\Database\Eloquent\Builder;

class CategoryFilter extends GroupAbstract
{
    protected CategoryManager $manager;

    public function __construct()
    {
        $this->manager = app(CategoryManager::class);
    }

    public function build(array $data = []): array
    {
        return $this->prepareFilterData($data, function() use ($data) {
            return $this->manager
                ->findAdminCategories($this->parseOutsideItemIds($data))
                ->map(function(Categories $cat) {
                    return [
                        'id' => $cat->id,
                        'name' => $cat->category_name,
                        'alias' => $cat->alias,
                    ];
                })
                ->when(!$data['hide_all'], $this->getAllItem())
                ->toArray();
        });
    }

    public function apply(Builder $builder, array $selectedFilter = []): Builder
    {
        if (isset($selectedFilter['values']) && $selectedFilter['values']) {
            $builder->join('product_categories AS pc', 'products.id', 'pc.product_id');
            $builder->whereIn('pc.category_id', $selectedFilter['values']);
        }

        return $builder;
    }
}
