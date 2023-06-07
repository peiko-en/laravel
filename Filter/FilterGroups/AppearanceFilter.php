<?php

declare(strict_types=1);

namespace App\Services\Filter\FilterGroups;

use App\Models\Appearance;
use App\Models\Managers\AppearanceManager;
use Illuminate\Database\Eloquent\Builder;

class AppearanceFilter extends GroupAbstract
{
    private AppearanceManager $appearanceManager;

    public function __construct()
    {
        $this->appearanceManager = app(AppearanceManager::class);
    }

    public function build(array $data = []): array
    {
        return $this->prepareFilterData($data, function() use ($data) {
            return $this->appearanceManager
                ->findAppearances($this->parseOutsideItemIds($data))
                ->map(function(Appearance $effect) {
                    return [
                        'id' => $effect->id,
                        'name' => $effect->name,
                        'alias' => $effect->alias
                    ];
                })
                ->when(!$data['hide_all'], $this->getAllItem())
                ->toArray();
        });
    }

    public function apply(Builder $builder, array $selectedFilter = []): Builder
    {
        if (isset($selectedFilter['values']) && $selectedFilter['values']) {
            $builder->whereIn('products.appearance_id', $selectedFilter['values']);
        }

        return $builder;
    }
}
