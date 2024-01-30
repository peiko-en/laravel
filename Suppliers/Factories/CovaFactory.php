<?php

namespace App\Services\Suppliers\Factories;

use App\Models\CovaCategory;
use App\Models\SupplierCategoryInterface;
use App\Services\Suppliers\CovaSupplier;
use App\Services\Suppliers\SupplierInterface;

class CovaFactory extends SupplierFactory
{
    public function getSupplier(): SupplierInterface
    {
        return new CovaSupplier($this->dispensary, $this->getCategory(), $this->supplier);
    }

    public function getCategory(): SupplierCategoryInterface
    {
        return new CovaCategory();
    }
}