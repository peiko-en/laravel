<?php
declare(strict_types=1);

namespace App\Services\Matching;


use App\Helpers\{DIHelper, Math};
use App\Models\{Managers\BotManager, Managers\MatchingOrderManager, Order, Pair};
use Illuminate\Redis\Connections\Connection;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Output\ConsoleOutput;

abstract class OrderBookAggregation
{
    const SIDE_SIZE = 50;
    /**
     * @var \Illuminate\Redis\Connections\PhpRedisConnection
     */
    protected Connection $redis;
    protected int $side = 0;
    protected int $pairId = 0;
    protected string $pair = '';
    protected int $mainPrecision = 0;
    protected int $basePrecision = 0;

    public function __construct(Connection $redis)
    {
        $this->redis = $redis;
    }

    public function setSide(int $side): self
    {
        $this->side = $side;
        return $this;
    }

    public function getSide(): int
    {
        return $this->side;
    }

    public function bids(): self
    {
        $this->setSide(Order::SIDE_BUY);
        return $this;
    }

    public function asks(): self
    {
        $this->setSide(Order::SIDE_SELL);
        return $this;
    }

    public function setMainPrecision(int $value): self
    {
        $this->mainPrecision = $value;
        return $this;
    }

    public function setBasePrecision(int $value): self
    {
        $this->basePrecision = $value;
        return $this;
    }

    public function setPairId(int $id): self
    {
        $this->pairId = $id;
        return $this;
    }

    public function setPair(string $pair): self
    {
        $this->pair = $pair;
        return $this;
    }

    public function initFromPair(Pair $pair): self
    {
        $this->setPairId($pair->id)
            ->setMainPrecision($pair->mainPrecision())
            ->setBasePrecision($pair->basePrecision())
            ->setPair($pair->getCode());

        return $this;
    }

    protected function key(): string
    {
        return $this->side . $this->pairId;
    }

    protected function createId($price): string
    {
        return str_replace('.', '', $this->key() . rand(10, 100) . $price);
    }

    public abstract function get(int $limit = self::SIDE_SIZE, int $start = 0): array;

    public abstract function priceCollectionFormat($prices): array;

    public abstract function add(float $price, float $quantity);

    public abstract function sub(float $price, float $quantity, bool $withNormalize = true);

    public abstract function clear();

    protected function baseClear()
    {
        $this->redis->del('orders' . $this->pairId . $this->side);
        DIHelper::liquidityBotOrderStorage()->removeAll($this->pairId, $this->side);

        MatchingOrderManager::orderQuery($this->side)
            ->where('pair_id', $this->pairId)
            ->delete();
    }

    public abstract function getByPrice(float $price, bool $remove = false): array;

    public abstract function getRangePrices(float $startPrice, float $finishPrice, float $limit = 200, int $offset = 0);

    public function getFormattedRangePrices(float $startPrice, float $finishPrice, float $limit = 200, int $offset = 0): array
    {
        $prices = $this->getRangePrices($startPrice, $finishPrice, $limit, $offset);
        if (!$prices) {
            $prices = [];
        }

        return $this->priceCollectionFormat($prices);
    }

    public function sync(bool $progress = false)
    {
        foreach ([Order::SIDE_SELL, Order::SIDE_BUY] as $side) {
            $this->setSide($side);
            $this->clear();
            $this->runSync($progress);
        }
    }

    public function recalculate()
    {
        foreach ([Order::SIDE_SELL, Order::SIDE_BUY] as $side) {
            $this->setSide($side);
            $this->redis->del($this->key());

            $scopes = ['kindLimit', ($this->side == Order::SIDE_SELL) ? 'sales' : 'purchase', 'open', 'matched'];

            /** @var Order[] $orders */
            $orders = Order::query()
                ->from('orders')
                ->select(['id', 'price', 'quantity'])
                ->where('pair_id', $this->pairId)
                ->scopes($scopes)
                ->cursor();

            foreach ($orders as $order) {
                $this->add((float) $order->price, (float) $order->quantity);
            }
        }
    }

    private function runSync(bool $progressStatus = false)
    {
        $scopes = ['kindLimit', ($this->side == Order::SIDE_SELL) ? 'sales' : 'purchase', 'open', 'matched'];

        $progress = $output = null;

        if ($progressStatus) {
            $total = Order::query()
                ->where('pair_id', $this->pairId)
                ->scopes($scopes)
                ->count();

            $output = new ConsoleOutput();
            $progress = new ProgressBar($output, $total);
            $output->writeln('');
            $progress->start();
        }

        $liquidityBotStorage = DIHelper::liquidityBotOrderStorage();
        $liquidBot = BotManager::findByPairId($this->pairId);
        $liqBotId = $liquidBot ? $liquidBot->liquidityBotId() : 0;

        /** @var Order[] $orders */
        $orders = Order::query()
            ->from('orders')
            ->select(['id', 'price', 'quantity', 'owner_id'])
            ->where('pair_id', $this->pairId)
            ->scopes($scopes)
            ->cursor();

        $matchOrdersInserts = [];
        $count = 0;
        $botOrdersCount = 0;
        $total = 0;

        foreach ($orders as $order) {
            $count++;
            $total++;

            $matchOrdersInserts[$order->id] = [
                'order_id' => $order->id,
                'pair_id' => $this->pairId,
                'price' => $order->price,
            ];

            $this->add((float) $order->price, (float) $order->quantity);

            if ($order->owner_id == $liqBotId) {
                $liquidityBotStorage->add($this->pairId, $this->side, (float) $order->price, $order->id);
                $botOrdersCount++;
            }

            if ($count == 2000) {
                MatchingOrderManager::orderQuery($this->side)->insert($matchOrdersInserts);
                $matchOrdersInserts = [];
                $count = 0;
            }

            if ($progress) {
                $progress->advance();
            }
        }

        if ($matchOrdersInserts) {
            MatchingOrderManager::orderQuery($this->side)->insert($matchOrdersInserts);
        }

        if ($progress) {
            $progress->finish();
        }

        if ($progressStatus) {
            $sideName = Order::sides()[$this->side];
            $output->writeln("\nAdded orders to match " . $sideName
                . ': ' . MatchingOrderManager::count($this->pairId, $this->side). '/' . $total
            );
            $output->writeln("\nAdded bot orders " . $sideName
                . ': ' . $liquidityBotStorage->size($this->pairId, $this->side) . '/' . $botOrdersCount
            );
        }
    }

    protected function isBids(): bool
    {
        return $this->side == Order::SIDE_BUY;
    }

    protected function isAsks(): bool
    {
        return $this->side == Order::SIDE_SELL;
    }

    protected function calcFillPercent($qty, $qtyMax)
    {
        return $qtyMax > 0 ? 100 - ceil((float) Math::div($qty * 100, $qtyMax)) : 0;
    }
}
