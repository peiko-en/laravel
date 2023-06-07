<?php

declare(strict_types=1);

namespace App\Services\Filter\FilterStructures;

interface StructuredFilterList
{
    public function fetchStructuredList(): array;
}
