<?php

declare(strict_types=1);

namespace App\Services\Filter\FilterGroups;

use App\Models\Managers\TerpeneManager;
use App\Models\Terpene;
use Illuminate\Database\Eloquent\Builder;

class TerpeneFilter extends GroupAbstract
{
    private TerpeneManager $terpeneManager;

    public function __construct()
    {
        $this->terpeneManager = app(TerpeneManager::class);
    }

    public function build(array $data = []): array
    {
        return $this->prepareFilterData($data, function() use ($data) {
            return $this->terpeneManager
                ->findTerpenes($this->parseOutsideItemIds($data))
                ->map(function(Terpene $effect) {
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
            $builder->join('product_terpenes AS pt', 'products.id', 'pt.product_id');
            $builder->whereIn('pt.terpene_id', $selectedFilter['values']);
        }

        return $builder;
    }
}
