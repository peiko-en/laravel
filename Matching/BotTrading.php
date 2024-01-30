<?php
declare(strict_types=1);

namespace App\Services\Matching;


use App\Helpers\Log;
use App\Events\{GraphEvent, OrderBookChangesEvent, PriceEvent};
use App\Helpers\Math;
use App\Models\Managers\{PairsManager, PricesManager};
use App\Models\Order;

class BotTrading
{
    private OrderBookAggregation $orderBookAgg;
    private ?Order $order = null;

    public function __construct(OrderBookAggregation $orderBookAgg)
    {
        $this->orderBookAgg = $orderBookAgg;
    }

    /**
     * @param Order $order order not saved in DB
     */
    public function trade(Order $order)
    {
        $this->order = $order;
        $this->orderBookAgg
            ->initFromPair($order->pair)
            ->setSide($order->side);

        $this->addToOrderBook();
        $order->createDeal($order, (float) $order->start_quantity);
        $this->broadcasting();

        $quantity = $this->order->start_quantity;

        $chunkQuantity = Math::div($quantity, rand(2, 3));
        $quantity = Math::sub($quantity, $chunkQuantity);

        $this->removeFromOrderBook((float) $chunkQuantity);

        if ($quantity > 0) {
            usleep(100000);
            $this->removeFromOrderBook((float) $quantity);
        }
    }

    private function addToOrderBook()
    {
        $this->orderBookAgg->add((float) $this->order->price, (float) $this->order->start_quantity);
        $this->orderBookEvent();
    }

    private function removeFromOrderBook(float $quantity)
    {
        $this->orderBookAgg->sub((float) $this->order->price, $quantity, false);
        $this->orderBookEvent();
    }

    private function orderBookEvent()
    {
        event(new OrderBookChangesEvent($this->orderBookAgg, $this->order->pairCode()));
    }

    private function broadcasting()
    {
        try {
            PairsManager::updatePrice($this->order->pair_id, (float) $this->order->price);
            PricesManager::insert($this->order->pair_id, (float) $this->order->price, (float) $this->order->start_quantity);

            $this->order->pair->setLastPrice((float) $this->order->price);
            event(new PriceEvent($this->order->pair, $this->order->start_quantity));
            event(new GraphEvent($this->order->pair_id, $this->order->pair->getCode(), $this->order->price, $this->order->start_quantity));
        } catch (\Exception $e) {
            Log::matching($e);
        }
    }

    public function closeHungOrder(Order $order)
    {
        $order->status = Order::STATUS_CANCELED;
        $order->fillFinishedDate();
        $order->save();

        $this->orderBookAgg->sub((float) $order->price, (float) $order->quantity, false);
    }
}
