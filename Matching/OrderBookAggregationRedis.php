<?php
declare(strict_types=1);

namespace App\Services\Matching;


use App\Helpers\{Format, Math};

class OrderBookAggregationRedis extends OrderBookAggregation
{
    public function get(int $limit = self::SIDE_SIZE, int $start = 0): array
    {
        if ($this->isBids()) {
            $data = $this->redis->zrevrange($this->key(), $start, $start + $limit);
        } else {
            $data = $this->redis->zrange($this->key(), $start, $start + $limit);
        }

        return $this->priceCollectionFormat($data);
    }

    public function priceCollectionFormat($prices): array
    {
        return collect($prices)->map(function($priceItem) {
            $priceItem = json_decode($priceItem, true);
            $priceItem['q'] = Format::amount($priceItem['q']);
            $priceItem['v'] = Format::amount($priceItem['v']);
            $priceItem['vf'] = Format::faceAmount($priceItem['v'], $this->basePrecision);
            return $priceItem;
        })->toArray();
    }

    public function add(float $price, float $quantity)
    {
        $data = $this->getByPrice($price, true);
        $data['q'] = (float) Math::add($data['q'], $quantity, $this->mainPrecision);
        $data['qm'] = (float) Math::add($data['qm'], $quantity, $this->mainPrecision);

        $this->set($price, $data);
    }

    public function sub(float $price, float $quantity, bool $withNormalize = true)
    {
        $data = $this->getByPrice($price, true);
        $data['q'] = (float) Math::sub($data['q'], $quantity, $this->mainPrecision);

        if ($data['q'] > 0) {
            $this->set($price, $data);
        }

        if ($withNormalize) {
            $this->normalize($price);
        }
    }

    public function clear()
    {
        $this->baseClear();
        $this->redis->del($this->key());
    }

    private function set(float $price, array $data)
    {
        if ($data['q'] > 0) {
            $data['pr'] = $this->calcFillPercent($data['q'], $data['qm']);
            $data['v'] = (float) Math::mul($data['q'], $price, $this->basePrecision);
            $data['id'] = $this->createId($price);

            $this->redis->zadd($this->key(), $this->scoreFormat($price), json_encode($data));
        }
    }

    public function getByPrice(float $price, bool $remove = false): array
    {
        $score = $this->scoreFormat($price);
        $restoredData = current($this->redis->zrangebyscore($this->key(), $score, $score));
        if ($restoredData) {
            $res = json_decode($restoredData, true);

            if ($remove) {
                $this->remove($restoredData);
            }

            return $res;
        }

        return [
            'p' => $price,
            'q' => 0,
            'qm' => 0,
            'pr' => 0,
            'v' => 0,
        ];
    }

    private function scoreFormat(float $price): int
    {
        return intval($price * 10 ** $this->basePrecision);
    }

    private function normalize(float $price)
    {
        $range = $this->getStuckPrices($price);

        if (count($range) > 1) {
            foreach (array_slice($range, 0, -1) as $item) {
                $this->remove($item);
            }
        }
    }

    private function getStuckPrices(float $price)
    {
        $first = $this->get(0);
        $startRangePrice = (float) ($first[0]['p'] ?? 0);
        if (Math::comp($startRangePrice, $price) == 0 || !$startRangePrice) {
            return [];
        }

        return $this->getRangePrices($startRangePrice, $price);
    }

    public function getRangePrices(float $startPrice, float $finishPrice, float $limit = 0, int $offset = 0)
    {
        $options = [];

        if ($limit > 0) {
            $options['limit'] = ['offset' => $offset, 'count' => $limit];
        }

        $startScore = $this->scoreFormat($startPrice);
        $finishScore = $this->scoreFormat($finishPrice);

        if ($this->isBids()) {
            return $this->redis->zrevrangebyscore($this->key(), $startScore, $finishScore, $options);
        } else {
            return $this->redis->zrangebyscore($this->key(), $startScore, $finishScore, $options);
        }
    }

    public function getFormattedRangePrices(float $startPrice, float $finishPrice, float $limit = 0, int $offset = 0): array
    {
        $prices = $this->getRangePrices($startPrice, $finishPrice, $limit, $offset);
        if (!$prices) {
            $prices = [];
        }

        return $this->priceCollectionFormat($prices);
    }

    private function remove(string $data)
    {
        $this->redis->zrem($this->key(), $data);
    }
}
