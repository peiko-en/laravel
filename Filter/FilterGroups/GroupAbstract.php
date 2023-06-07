<?php

declare(strict_types=1);

namespace App\Services\Filter\FilterGroups;

use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\{Arr, Collection};

abstract class GroupAbstract
{
    protected int $categoryId = 0;
    protected array $allItem = ['id' => 0, 'name' => 'All', 'alias' => 'all'];

    public function setCategoryId(int $categoryId): self
    {
        $this->categoryId = $categoryId;

        return $this;
    }
    abstract public function build(array $data = []): array;

    abstract public function apply(Builder $builder, array $selectedFilter = []): Builder;

    protected function prepareFilterData(array $data, ?Closure $itemsCallable = null): array
    {
        return array_merge(
            Arr::only($data, ['id', 'name', 'alias', 'kind', 'folding']),
            $this->getLimit($data),
            [
                'items' => $itemsCallable ? $itemsCallable() : $data['items']
            ]
        );
    }

    protected function getLimit(array $data): array
    {
        return [
            'limit' => [
                'value' => $data['stint_value'] ?? 10,
                'title' => trans('filter.show-all-type', ['type' => $data['name']]),
            ]
        ];
    }

    protected function parseOutsideItemIds(array $data): array
    {
        return empty($data['outside_ids']) ? [] : explode(',', $data['outside_ids']);
    }

    protected function getAllItem(): Closure
    {
        return fn(Collection $items) => $items->prepend($this->allItem);
    }
}
