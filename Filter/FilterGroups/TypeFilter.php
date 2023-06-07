<?php

declare(strict_types=1);

namespace App\Services\Filter\FilterGroups;

use App\Models\Managers\ProductTypeManager;
use App\Models\Product_types;
use Illuminate\Database\Eloquent\Builder;

class TypeFilter extends GroupAbstract
{
    private ProductTypeManager $typeManager;

    public function __construct()
    {
        $this->typeManager = app(ProductTypeManager::class);
    }

    public function build(array $data = []): array
    {
        return $this->prepareFilterData($data, function() use ($data) {
            return $this->typeManager
                ->findTypes($this->parseOutsideItemIds($data))
                ->map(function(Product_types $productType) {
                    return [
                        'id' => $productType->id,
                        'name' => $productType->product_type,
                        'alias' => $productType->alias,
                    ];
                })
                ->when(!$data['hide_all'], $this->getAllItem())
                ->toArray();
        });
    }

    public function apply(Builder $builder, array $selectedFilter = []): Builder
    {
        if (isset($selectedFilter['values']) && $selectedFilter['values']) {
            $builder->whereIn('products.product_type', $selectedFilter['values']);
        }

        return $builder;
    }
}
