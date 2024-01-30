<?php
declare(strict_types=1);

namespace App\Services\Matching;


use App\Helpers\DIHelper;
use App\Models\{Order, Pair};
use App\Services\Bot\LiquidityBotOrderStorage;
use Illuminate\Database\Eloquent\Collection;

class ClosingLiquidBotOrders
{
    private MatchingEngine $engine;
    private LiquidityBotOrderStorage $botStorage;
    private ?string $pairCode = null;

    public function __construct(MatchingEngine $engine)
    {
        $this->engine = $engine;
        $this->botStorage = DIHelper::liquidityBotOrderStorage();
    }

    public function handle(array $orderIds, int $pairId = 0)
    {
        $orders = $this->getOrders($orderIds);
        if ($orders->isEmpty()) {
            if ($orderIds && $pairId > 0) {
                $this->botStorage->removeBothSidesIds($pairId, $orderIds);
            }
            return ;
        }

        $removeIds = $cancelIds = $completedOrderIds = [];
        /** @var Pair $pair */
        $pair = $orders->first()->pair;
        $this->pairCode = $pair->getCode();

        foreach ($orders as $order) {
            $order->setRelation('pair', $pair);

            if ($order->isCompleted()) {
                $completedOrderIds[] = $order->id;
                continue;
            }

            if ($order->isPending() && $order->completedQuantity() == 0) {
                $removeIds[] = $order->id;
            } else {
                $cancelIds[] = $order->id;
            }

            $this->engine->removeFromOrderBook($order);
        }

        $this->engine->removeCollectionFromOrderBook($orders->all());

        if ($removeIds) {
            $this->removeByIds($removeIds);
        }

        if ($cancelIds) {
            $this->cancelByIds($cancelIds);
        }

        if ($removeIds || $cancelIds) {
            $this->events();
        }

        $this->botStorage->removeBothSidesIds($pair->id, $orderIds);
    }

    private function events()
    {
        if ($this->pairCode) {
            $this->engine->orderBookChangeEvent($this->pairCode);
        }
    }

    /**
     * @return Collection|Order[]
     */
    private function getOrders(array $orderIds): Collection
    {
        return Order::query()
            ->whereIn('id', $orderIds)
            ->get();
    }

    private function removeByIds(array $ids)
    {
        Order::query()->whereIn('id', $ids)->delete();
    }

    private function cancelByIds(array $ids)
    {
        Order::query()
            ->whereIn('id', $ids)
            ->update([
                'status' => Order::STATUS_CANCEL_PARTLY,
                'finished_at' => now(),
            ]);
    }
}
