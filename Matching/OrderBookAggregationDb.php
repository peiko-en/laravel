<?php
declare(strict_types=1);

namespace App\Services\Matching;


use App\Helpers\{Format, Math};
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class OrderBookAggregationDb extends OrderBookAggregation
{
    private function queryPrice(float $price): \Illuminate\Database\Query\Builder
    {
        return $this->query()->where('price', $price);
    }

    private function query(): \Illuminate\Database\Query\Builder
    {
        return $this->tableQuery()->where('pair_id', $this->pairId);
    }

    private function tableQuery(): \Illuminate\Database\Query\Builder
    {
        return DB::table($this->isBids() ? 'order_book_bids' : 'order_book_asks');
    }

    public function get(int $limit = self::SIDE_SIZE, int $start = 0): array
    {
        return $this->priceCollectionFormat($this->query()
            ->limit($limit)
            ->offset($start)
            ->orderBy('price', $this->isAsks() ? 'asc' : 'desc')
            ->get());
    }

    /**
     * @param Collection $prices
     * @return array
     */
    public function priceCollectionFormat($prices): array
    {
        return $prices->map(function($item) {
            $volume = Math::mul($item->price, $item->quantity, $this->basePrecision);
            return [
                'p' => Format::amount($item->price),
                'q' => Format::amount($item->quantity),
                'qm' => $item->quantity_max,
                'v' => $volume,
                'vf' => Format::faceAmount($volume, $this->basePrecision),
                'pr' => $this->calcFillPercent($item->quantity, $item->quantity_max),
                'id' => $this->createId($item->price)
            ];
        })->toArray();
    }

    public function add(float $price, float $quantity)
    {
        $updCount = $this->queryPrice($price)
            ->update([
                'quantity' => DB::raw('quantity + ' . $quantity),
                'quantity_max' => DB::raw('quantity_max + ' . $quantity),
            ]);

        if ($updCount == 0) {
            $this->tableQuery()->insert([
                'pair_id' => $this->pairId,
                'price' => $price,
                'quantity' => $quantity,
                'quantity_max' => $quantity,
            ]);
        }
    }

    public function sub(float $price, float $quantity, bool $withNormalize = true)
    {
        $item = $this->queryPrice($price)->lockForUpdate()->first();
        if (!$item) {
            return ;
        }

        $item->quantity = (float) Math::sub($item->quantity, $quantity, $this->mainPrecision);

        if ($item->quantity > 0) {
            $this->queryPrice($price)->update(['quantity' => $item->quantity]);
        } else {
            $this->queryPrice($price)->delete();
        }
    }

    public function clear()
    {
        $this->baseClear();
        $this->query()->delete();
    }

    public function getByPrice(float $price, bool $remove = false): array
    {
        $item = $this->query()->where('price', $price)->first();

        $qty = $item->quantity ?? 0;
        $qtyMax = $item->quantity_max ?? 0;

        return [
            'p' => $price,
            'q' => $qty,
            'qm' => $qtyMax,
            'pr' => $this->calcFillPercent($qty, $qtyMax),
            'v' => Math::mul($price, $qty, $this->basePrecision)
        ];
    }

    public function getRangePrices(float $startPrice, float $finishPrice, float $limit = 200, int $offset = 0)
    {
        return $this->query()
            ->limit($limit)
            ->offset($offset)
            ->orderBy('price', $this->isAsks() ? 'asc' : 'desc')
            ->get()
            ->filter(function($item) use ($startPrice, $finishPrice) {
                $price  = floatval($item->price);
                if ($this->isAsks() && $price >= $startPrice && $price <= $finishPrice) {
                    return true;
                } elseif ($this->isBids() && $price <= $startPrice && $price >= $finishPrice) {
                    return true;
                }

                return false;
            })
            ->values();
    }

    public function getFormattedRangePrices(float $startPrice, float $finishPrice, float $limit = 200, int $offset = 0): array
    {
        $prices = $this->getRangePrices($startPrice, $finishPrice, $limit, $offset);
        if (!$prices) {
            $prices = [];
        }

        return $this->priceCollectionFormat($prices);
    }
}
