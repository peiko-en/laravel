<?php

declare(strict_types=1);

namespace App\Services\Filter\Types;

use Illuminate\Database\Eloquent\Builder;

interface FilterInterface
{
    public function load(): void;

    public function parse(string $selectedFilters): void;

    public function apply(Builder $builder): Builder;
}
