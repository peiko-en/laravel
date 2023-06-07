<?php

declare(strict_types=1);

namespace App\Services\Filter\FilterGroups;

use App\Enums\FilterType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\JoinClause;
use Illuminate\Support\{Arr, Str};

class CommonFilter extends GroupAbstract
{
    public function build(array $data = []): array
    {
        return $this->prepareFilterData($data, function() use ($data) {
            return collect($data['items'])
                ->map(function(array $item) use ($data) {
                    $itemData = Arr::only($item, ['id', 'name', 'alias']);

                    if ($data['kind'] == FilterType::SLIDER->value) {
                        $itemData['params'] = $item['params'];
                    }

                    return $itemData;
                })
                ->when(!$data['hide_all'], $this->getAllItem())
                ->toArray();
        });
    }

    public function apply(Builder $builder, array $selectedFilter = []): Builder
    {
        if ($selectedFilter['type'] == FilterType::SLIDER) {
            foreach ($selectedFilter['values'] as $param => $values) {
                $builder->where('products.min_' . $param, '>=', $values['min']);
                $builder->where('products.max_' . $param, '<=', $values['max']);
            }
        } elseif ($selectedFilter['type'] == FilterType::CHECKBOX) {
            $prefix = 'prodfs_' . Str::random(5);
            $builder->join('product_filter_assign AS ' . $prefix, function(JoinClause $join) use ($selectedFilter, $prefix) {
                $join->on('products.id', '=', $prefix . '.product_id');
                $join->whereIn($prefix . '.product_filter_id', $selectedFilter['values']);
            });
        }

        return $builder;
    }
}
