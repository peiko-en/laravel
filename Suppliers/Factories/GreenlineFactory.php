<?php

namespace App\Services\Suppliers\Factories;

use App\Models\GreenlineCategory;
use App\Models\SupplierCategoryInterface;
use App\Services\Suppliers\GreenlineSupplier;
use App\Services\Suppliers\SupplierInterface;

class GreenlineFactory extends SupplierFactory
{
    public function getSupplier(): SupplierInterface
    {
        return new GreenlineSupplier($this->dispensary, $this->getCategory(), $this->supplier);
    }

    public function getCategory(): SupplierCategoryInterface
    {
        return new GreenlineCategory();
    }
}