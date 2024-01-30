<?php

namespace App\Services\Suppliers;

interface SupplierInterface
{
    public function syncProducts(): void;

    public function syncInventory(): void;

    public function getTotal(): int;

    public function getProcessed(): int;
}