<?php
declare(strict_types=1);

namespace App\Services\Matching;


use App\Helpers\Math;

class MarketQuantityEstimation
{
    private float $total;
    private float $quantity;
    private float $scannedQuantity = 0;
    private float $lastUsedPrice;
    private float $restTotal = 0;

    public function __construct(float $total)
    {
        $this->total = $total;
    }

    public function setQuantity(float $quantity): self
    {
        $this->quantity = $quantity;
        return $this;
    }

    public function setLastUsedPrice(float $lastUsedPrice): self
    {
        $this->lastUsedPrice = $lastUsedPrice;
        return $this;
    }

    public function getQuantity(): float
    {
        return $this->quantity;
    }

    public function setRestTotal(float $restTotal)
    {
        $this->restTotal = $restTotal;
    }

    public function haveResidue(): bool
    {
        return $this->restTotal > 0;
    }

    public function hasMoreQuantityOnMarket(): bool
    {
        return $this->scannedQuantity > $this->quantity;
    }

    public function getUsedTotal(): float
    {
        return (float) Math::sub($this->total, $this->restTotal);
    }

    public function countScannedQuantity($quantity)
    {
        $this->scannedQuantity  = (float) Math::add($this->scannedQuantity, $quantity);
    }
}
