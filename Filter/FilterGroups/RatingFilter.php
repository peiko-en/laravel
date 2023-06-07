<?php

declare(strict_types=1);

namespace App\Services\Filter\FilterGroups;

use Illuminate\Database\Eloquent\Builder;

class RatingFilter extends GroupAbstract
{
    public function build(array $data = []): array
    {
        return $this->prepareFilterData($data, function() use ($data) {
            return collect([4, 3, 2, 1, 0])
                ->map(function($id, $index) {
                    return [
                        'id' => $index + 1,
                        'name' => $id > 0 ? $id . '+' : 'No reviews',
                        'alias' => (string) $id
                    ];
                })
                ->when(!$data['hide_all'], $this->getAllItem())
                ->toArray();
        });
    }

    public function apply(Builder $builder, array $selectedFilter = []): Builder
    {
        if (isset($selectedFilter['aliases']) && $selectedFilter['aliases']) {
            $rating = (int) $selectedFilter['aliases'][0];
            $operator = $rating > 0 ? '>=' : '=';
            $builder->where('brands.rating', $operator, $rating);
        }

        return $builder;
    }
}
