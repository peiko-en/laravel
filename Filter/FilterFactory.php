<?php

declare(strict_types=1);

namespace App\Services\Filter;

use App\Services\Filter\FilterGroups\{SubCategoryFilter};
use App\Services\Filter\FilterGroups\AppearanceFilter;
use App\Services\Filter\FilterGroups\BrandCategoryFilter;
use App\Services\Filter\FilterGroups\BrandFilter;
use App\Services\Filter\FilterGroups\CategoryFilter;
use App\Services\Filter\FilterGroups\CommonFilter;
use App\Services\Filter\FilterGroups\EffectFilter;
use App\Services\Filter\FilterGroups\GroupAbstract;
use App\Services\Filter\FilterGroups\RatingFilter;
use App\Services\Filter\FilterGroups\TerpeneFilter;
use App\Services\Filter\FilterGroups\TypeFilter;

class FilterFactory
{
    public function getInstance(string $alias): GroupAbstract
    {
        return match ($alias) {
            'brand' => new BrandFilter(),
            'cat' => new CategoryFilter(),
            'bcat' => new BrandCategoryFilter(),
            'subcat' => new SubCategoryFilter(),
            'type' => new TypeFilter(),
            'effect' => new EffectFilter(),
            'terpene' => new TerpeneFilter(),
            'appearance' => new AppearanceFilter(),
            'rating' => new RatingFilter(),
            default => new CommonFilter(),
        };
    }
}
