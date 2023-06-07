<?php

declare(strict_types=1);

namespace App\Services\Filter\Types;

use App\Enums\FilterType;
use App\Services\Sorting\Sorting;
use Illuminate\Support\Arr;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

abstract class FilterAbstract implements FilterInterface
{
    private const GROUP_SEPARATOR = ';';
    private const FILTER_ITEM_SEPARATOR = '_';
    private const RANGE_SEPARATOR = ',';
    protected const SORT_PARAM = 'sort';

    protected array $selectedFilters = [];
    protected array $filters = [];
    /**
     * @var Sorting[]
     */
    protected array $sorts = [];
    protected Sorting $activeSort;

    public function parse(string $selectedFilters): void
    {
        if ($selectedFilters) {
            $this->load();
        } else {
            return;
        }

        $this->selectedFilters = array_map(function(string $items) {
            $groupItems = array_filter(explode(self::FILTER_ITEM_SEPARATOR, $items), fn($item) => $item != 'all');

            if ($groupItems[0] == self::SORT_PARAM) {
                if (!isset($this->sorts[$groupItems[1]])) {
                    throw new NotFoundHttpException(trans('err.sort-not-found', ['type' => $groupItems[1]]));
                }

                $this->activeSort = $this->sorts[$groupItems[1]];
                return [
                    'group' => $groupItems[0],
                    'aliases' => [$groupItems[1]],
                    'values' => [$groupItems[1]],
                ];
            }

            if (!isset($this->filters[$groupItems[0]])) {
                throw new NotFoundHttpException(trans('filter-group-doesnt-exist', ['group' => $groupItems[0]]));
            }

            $aliases = array_slice($groupItems, 1);
            $type = FilterType::tryFrom($this->filters[$groupItems[0]]['kind']) ?: FilterType::CHECKBOX;

            return [
                'group' => $groupItems[0],
                'type' => $type,
                'aliases' => $aliases,
                'values' => $this->parseSelectedValues($groupItems[0], $aliases, $type),
            ];
        }, explode(self::GROUP_SEPARATOR, $selectedFilters));
    }

    private function parseSelectedValues(string $group, array $selectedAliases, FilterType $type): array
    {
        $groupItems = Arr::pluck($this->filters[$group]['items'], null, 'alias');

        return match ($type) {
            FilterType::CHECKBOX => $this->parseCheckboxValues($groupItems, $selectedAliases),
            FilterType::SLIDER => $this->parseSliderValues($groupItems, $selectedAliases),
        };
    }

    private function parseCheckboxValues(array $groupItems, array $selectedAliases): array
    {
        return array_map(function($alias) use ($groupItems) {
            $value = $groupItems[$alias]['id'] ?? 0;
            if ($value == 0) {
                throw new NotFoundHttpException(trans('sys.at-least-one-filter-undefined'));
            }

            return $value;

        }, $selectedAliases);
    }

    private function parseSliderValues(array $groupItems, array $selectedAliases): array
    {
        $values = [];
        foreach ($selectedAliases as $alias) {
            $range = explode(self::RANGE_SEPARATOR, $alias);
            if (!isset($groupItems[$range[0]]) || !Arr::has($range, [1, 2])) {
                throw new NotFoundHttpException(trans('err.filter-undefined', ['type' => $alias]));
            }

            $values[$range[0]] = ['min' => (int) $range[1], 'max' => (int) $range[2]];
        }

        return $values;
    }
}
