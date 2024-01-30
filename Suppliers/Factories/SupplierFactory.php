<?php

namespace App\Services\Suppliers\Factories;

use App\Models\Dispensary;
use App\Models\SupplierCategoryInterface;
use App\Services\Suppliers\SupplierInterface;
use App\Services\Suppliers\SupplierType;
use Illuminate\Database\Eloquent\Model;

abstract class SupplierFactory
{
    protected Dispensary $dispensary;
    protected SupplierType $supplier;

    public static function instance(Dispensary $dispensary, string $alias): self
    {
        return match ($alias) {
            SupplierType::GREENLINE->value => new GreenlineFactory($dispensary, SupplierType::GREENLINE),
            SupplierType::COVA->value => new CovaFactory($dispensary, SupplierType::COVA),
            default => throw new \RuntimeException('Unknown supplier alias')
        };
    }

    public function __construct(Dispensary $dispensary, SupplierType $supplier)
    {
        $this->dispensary = $dispensary;
        $this->supplier = $supplier;
    }

    abstract public function getSupplier(): SupplierInterface;

    /**
     * @return SupplierCategoryInterface|Model
     */
    abstract public function getCategory(): SupplierCategoryInterface;
}