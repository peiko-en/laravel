<?php

declare(strict_types=1);

namespace App\Services\Filter\FilterGroups;

use App\Models\Categories;

class SubCategoryFilter extends CategoryFilter
{
    public function build(array $data = []): array
    {
        return $this->prepareFilterData($data, function() use ($data) {
            if (!$this->categoryId) {
                return [];
            }

            return $this->manager
                ->findAdminSubCategories($this->categoryId, $this->parseOutsideItemIds($data))
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
}
