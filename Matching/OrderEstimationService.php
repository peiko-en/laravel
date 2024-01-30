<?php
declare(strict_types=1);

namespace App\Services\Matching;


use App\Helpers\{DIHelper, Math};
use App\Models\Pair;

class OrderEstimationService
{
    private OrderBookAggregation $orderBookAgg;
    private Pair $pair;

    public function __construct(Pair $pair)
    {
        $this->pair = $pair;
        $this->orderBookAgg = DIHelper::orderBookAgg()
            ->initFromPair($pair);
    }

    public function quantityFromTotal(float $total, int $side): MarketQuantityEstimation
    {
        $this->orderBookAgg->setSide($side);

        $start = $quantity = 0;
        $leftTotal = $total;

        $quantityEstimation = new MarketQuantityEstimation($total);

        while ($offers = $this->orderBookAgg->get(20, $start)) {
            foreach ($offers as $offer) {
                $quantityEstimation->countScannedQuantity((float) $offer['q']);

                if ($offer['v'] > $leftTotal) {
                    $interimQuantity = Math::div($leftTotal, $offer['p'], $this->pair->getQuantityDecimals());
                    $interimTotal = Math::mulRoundUp($interimQuantity, $offer['p'], $this->pair->basePrecision());
                } else {
                    $interimQuantity = $offer['q'];
                    $interimTotal = $offer['v'];
                }

                if ((float) $interimQuantity == 0) {
                    break;
                }

                $leftTotal = Math::sub($leftTotal, $interimTotal, $this->pair->basePrecision());
                $quantity = Math::add($quantity, $interimQuantity, $this->pair->getQuantityDecimals());

                $quantityEstimation->setLastUsedPrice((float) $offer['p']);
            }

            if ($leftTotal > 0) {
                $start += count($offers);
            } else {
                break;
            }
        }

        $quantityEstimation->setQuantity((float) $quantity);
        $quantityEstimation->setRestTotal((float) $leftTotal);

        return $quantityEstimation;
    }

    public function findOutCost(float $quantity, int $side): float
    {
        $this->orderBookAgg->setSide($side);

        $start = $cost = 0;
        $leftQuantity = $quantity;

        while (true) {
            $offers = $this->orderBookAgg->get(20, $start);

            if (!$offers) {
                return 0;
            }

            foreach ($offers as $offer) {
                if ($offer['q'] > $leftQuantity) {
                    $interimQuantity = $leftQuantity;
                } else {
                    $interimQuantity = $offer['q'];
                }

                $leftQuantity = Math::sub($leftQuantity, $interimQuantity, $this->pair->mainPrecision());
                $cost = Math::add($cost, Math::mulRoundUp($interimQuantity, $offer['p']));
            }

            if ($leftQuantity > 0) {
                $start += count($offers);
            } else {
                break;
            }
        }

        return (float) $cost;
    }

    public function checkQuantityAvailability(float $quantity, int $side): bool
    {
        $this->orderBookAgg->setSide($side);

        $start = 0;
        $leftQuantity = $quantity;

        while (true) {
            $offers = $this->orderBookAgg->get(20, $start);

            if (!$offers) {
                return false;
            }

            foreach ($offers as $offer) {
                $interimQuantity = $offer['q'] > $leftQuantity ? $leftQuantity : $offer['q'];
                $leftQuantity = Math::sub($leftQuantity, $interimQuantity, $this->pair->mainPrecision());
            }

            if ($leftQuantity > 0) {
                $start += count($offers);
            } else {
                break;
            }
        }

        return true;
    }
}
