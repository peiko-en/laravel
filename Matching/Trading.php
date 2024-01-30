<?php
declare(strict_types=1);

namespace App\Services\Matching;


use App\Events\{DeepChartEvent, GraphEvent, OrderBookChangesEvent, PriceEvent};
use App\Helpers\{Converter, DIHelper, Log, Math};
use App\Models\{Currency, Managers\PairsManager, Managers\PricesManager, Order, User};

class Trading
{
    private OrderBook $orderBook;
    private OrderBookAggregation $orderBookAgg;
    private Order $takerOrder;
    private BargainProcess $bargainProcess;

    private float $lastPrice;
    private float $intermediatePrice = 0;
    private float $intermediatePriceVolume = 0;
    private int $mainCurrencyPrecision = 0;
    private int $baseCurrencyPrecision = 0;
    private float $btcRate = 0;
    private Currency $mainCurrency;
    private Currency $baseCurrency;
    private array $userVolumes = [];

    public function __construct(OrderBook $orderBook, OrderBookAggregation $orderBookAgg)
    {
        $this->orderBook = $orderBook;
        $this->orderBookAgg = $orderBookAgg;
        $this->takerOrder = new Order();
        $this->bargainProcess = new BargainProcess();
    }

    public function trade(Order $takerOrder)
    {
        $this->init($takerOrder);

        $this->takerOrder->matched();
        $makerOrders = $this->orderBook->takeMakerOrders($this->takerOrder->pair_id, $this->takerOrder->oppositeSide());

        foreach ($makerOrders as $makerOrder) {
            if (!$makerOrder->isInWork()) {
                $this->orderBook->remove($makerOrder);
                $makerOrder->checkOnCompleted();

                continue;
            }

            if (!$this->suitableForMatching($makerOrder)) {
                break;
            }

            if (!$this->tradeProcess($makerOrder) || $makerOrder->isInWork() || !$this->takerOrder->isInWork()) {
                break;
            }
        }

        $this->afterMatching();
    }

    private function afterMatching()
    {
        try {
            $this->closeMarketOrder();
            $this->addToMakerOrders();

            if ($this->takerOrder->isDirty()) {
                $this->takerOrder->save();
            }

            $this->broadcastPrice();
            $this->saveUserVolumes();
            $this->orderBookChangeEvent($this->takerOrder->pair->getCode());
            event(new DeepChartEvent($this->takerOrder->pair));
        } catch (\Exception $e) {
            Log::matching('afterMatching:begin broadcastPrice: ' . $e->getMessage());
        }
    }

    private function addToMakerOrders()
    {
        if ($this->takerOrder->isLimit() && $this->takerOrder->isInWork()) {
            if ($this->orderBook->add($this->takerOrder)) {
                $this->orderBookAgg
                    ->setSide($this->takerOrder->side)
                    ->add((float) $this->takerOrder->price, (float) $this->takerOrder->quantity);
            }
        }
    }

    private function closeMarketOrder()
    {
        if ($this->takerOrder->isMarket() && $this->takerOrder->isInWork()) {
            if ($this->takerOrder->completedQuantity() > 0) {
                $this->takerOrder->partlyComplete();
            } else {
                $this->takerOrder->cancel();
            }
        }
    }

    public function cancel(Order $order)
    {
        if ($order->isWaitCanceling()) {
            $order->cancel();
            $this->orderBook->remove($order);
        }
    }

    public function remove(Order $order)
    {
        if ($order->delete()) {
            $this->removeFromOrderBook($order);
        }
    }

    public function removeFromOrderBook(Order $order)
    {
        $this->orderBook->remove($order);
        $this->removeFromOrderBookAgg($order);
    }

    /**
     * @param Order[] $orders
     */
    public function removeBatchFromOrderBook(array $orders)
    {
        $this->orderBook->removeBatch($orders);

        foreach ($orders as $order) {
            $this->removeFromOrderBookAgg($order);
        }
    }

    public function removeFromOrderBookAgg(Order $order)
    {
        $this->orderBookAgg
            ->setSide($order->side)
            ->initFromPair($order->pair)
            ->sub((float) $order->price, (float) $order->quantity, false);
    }

    private function init(Order $takerOrder)
    {
        $this->takerOrder = $takerOrder;
        $this->lastPrice = $this->takerOrder->pair->getLastPrice();
        $this->mainCurrency = $this->takerOrder->mainCurrency();
        $this->baseCurrency = $this->takerOrder->baseCurrency();
        $this->mainCurrencyPrecision = $this->mainCurrency->getPrecision();
        $this->baseCurrencyPrecision = $this->baseCurrency->getPrecision();

        $this->intermediatePrice = 0;
        $this->intermediatePriceVolume = 0;

        $this->btcRate = Converter::toBtcRate($this->mainCurrency->code, $this->takerOrder->pair);

        $this->orderBookAgg
            ->setPairId($this->takerOrder->pair_id)
            ->setMainPrecision($this->mainCurrencyPrecision)
            ->setBasePrecision($this->baseCurrencyPrecision);
    }

    private function suitableForMatching(Order $makerOrder): bool
    {
        if ($this->takerOrder->isCompleted()) {
            return false;
        }

        if ($this->takerOrder->isMarket()) {
            return true;
        }

        if ($this->takerOrder->isBuy()) {
            return $makerOrder->price <= $this->takerOrder->price;
        } else {
            return $makerOrder->price >= $this->takerOrder->price;
        }
    }

    private function tradeProcess(Order $makerOrder): bool
    {
        $status = $this->bargainProcess
            ->reset()
            ->setTakerOrder($this->takerOrder)
            ->setMakerOrder($makerOrder)
            ->run();

        if (!$status) {
            return false;
        }

        $dealQuantity = $this->bargainProcess->getDealQuantity();

        $this->orderBookAgg
            ->setSide($makerOrder->side)
            ->sub((float) $makerOrder->price, $dealQuantity);

        if (!$makerOrder->isInWork()) {
            $this->orderBook->remove($makerOrder);
        }

        if (!$this->bargainProcess->getBuyer()->isBot()) {
            $this->incrementVolume($this->bargainProcess->getBuyer(), $dealQuantity);
        }

        if (!$this->bargainProcess->getSeller()->isBot()) {
            $this->incrementVolume($this->bargainProcess->getSeller(), $dealQuantity);
        }

        event(new GraphEvent($this->takerOrder->pair_id, $this->takerOrder->pair->getCode(), $makerOrder->price, $dealQuantity));

        $this->intermediatePrice = (float) $makerOrder->price;
        $this->intermediatePriceVolume = (float) Math::add($this->intermediatePriceVolume, $dealQuantity, $this->mainCurrencyPrecision);

        return true;
    }

    private function incrementVolume(User $user, float $volume)
    {
        $volumeBtc = Math::roundDown($volume * $this->btcRate, 8);

        if (!isset($this->userVolumes[$user->id])) {
            $this->userVolumes[$user->id] = (float) $user->trade_volume;
        }

        $this->userVolumes[$user->id] = (float) Math::add($this->userVolumes[$user->id], $volumeBtc);
    }

    private function broadcastPrice()
    {
        if ($this->lastPrice != $this->intermediatePrice && $this->intermediatePrice > 0) {
            $this->lastPrice = $this->intermediatePrice;
            $this->takerOrder->pair->setLastPrice($this->intermediatePrice);

            PricesManager::insert($this->takerOrder->pair_id, $this->intermediatePrice, $this->intermediatePriceVolume);
            PairsManager::updatePrice($this->takerOrder->pair_id, $this->intermediatePrice);

            event(new PriceEvent($this->takerOrder->pair, $this->intermediatePriceVolume));
        } elseif ($this->intermediatePriceVolume > 0) {
            PricesManager::updateVolume($this->takerOrder->pair_id, $this->intermediatePriceVolume, $this->intermediatePrice);
        }
    }

    private function saveUserVolumes()
    {
        foreach ($this->userVolumes as $uid => $volume) {
            User::query()->where('id', $uid)->update(['trade_volume' => $volume]);
        }

        $this->userVolumes = [];
    }

    public function orderBookChangeEvent(string $pair)
    {
        event(new OrderBookChangesEvent($this->orderBookAgg, $pair));
    }
}
