<?php

namespace App\Services\Suppliers;

use App\Models\CovaCategory;
use App\Models\Dispensary;
use App\Models\GreenlineCategory;

class CategorySupplierFactory
{
    public static function instance(string $alias): SupplierInterface
    {
        $class = match ($alias) {
            SupplierType::GREENLINE->value => GreenlineCategory::class,
            SupplierType::COVA->value => CovaCategory::class,
            default => throw new \RuntimeException('Unknown supplier alias')
        };

        return app($class);
    }
}