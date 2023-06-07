<?php

declare(strict_types=1);

namespace App\Services\Filter\FilterGroups;

use App\Models\Brand;
use App\Models\Managers\BrandManager;
use Illuminate\Database\Eloquent\Builder;

class BrandFilter extends GroupAbstract
{
    private BrandManager $brandManager;

    public function __construct()
    {
        $this->brandManager = app(BrandManager::class);
    }

    public function build(array $data = []): array
    {
        return $this->prepareFilterData($data, function() use ($data) {
            return $this->brandManager
                ->findBrands($this->parseOutsideItemIds($data))
                ->map(function(Brand $brand) {
                    return [
                        'id' => $brand->id,
                        'name' => $brand->name,
                        'alias' => $brand->alias,
                    ];
                })
                ->when(!$data['hide_all'], $this->getAllItem())
                ->toArray();
        });
    }

    public function apply(Builder $builder, array $selectedFilter = []): Builder
    {
        if (isset($selectedFilter['values']) && $selectedFilter['values']) {
            $builder->whereIn('products.brand_id', $selectedFilter['values']);
        }

        return $builder;
    }
}
