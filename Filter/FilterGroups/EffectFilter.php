<?php

declare(strict_types=1);

namespace App\Services\Filter\FilterGroups;

use App\Models\Effect;
use App\Models\Managers\EffectManager;
use Illuminate\Database\Eloquent\Builder;

class EffectFilter extends GroupAbstract
{
    private EffectManager $effectManager;

    public function __construct()
    {
        $this->effectManager = app(EffectManager::class);
    }

    public function build(array $data = []): array
    {
        return $this->prepareFilterData($data, function() use ($data) {
            return $this->effectManager
                ->findEffects($this->parseOutsideItemIds($data))
                ->map(function(Effect $effect) {
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
            $builder->join('product_effects AS pe', 'products.id', 'pe.product_id');
            $builder->whereIn('pe.effect_id', $selectedFilter['values']);
        }

        return $builder;
    }
}
