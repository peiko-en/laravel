<?php
declare(strict_types=1);

namespace App\Services\Matching;


use App\Models\Order;
use Illuminate\Support\Collection;

class OrderBook
{
    const TYPE_BIDS = 'bids';
    const TYPE_ASKS = 'asks';
    /**
     * @var array|Collection[][]
     */
    private array $orders = [];

    /**
     * @param int $pairId
     * @param int $side
     * @return Order[]
     */
    public function takeMakerOrders(int $pairId, int $side): OrdersLazyCollection
    {
        if ($side == Order::SIDE_SELL) {
            $orders = $this->getOrders($pairId, self::TYPE_ASKS);
            if (!$orders) {
                $orders = $this->fetchAsks($pairId);
            }
            return $orders;
        } else {
            $orders = $this->getOrders($pairId, self::TYPE_BIDS);
            if (!$orders) {
                $orders = $this->fetchBids($pairId);
            }

            return $orders;
        }
    }

    private function fetchAsks(int $pairId): OrdersLazyCollection
    {
        $orders = new OrdersLazyCollection($pairId, Order::SIDE_SELL);
        $this->setOffers($pairId, self::TYPE_ASKS, $orders);

        return $orders;
    }

    private function fetchBids(int $pairId): OrdersLazyCollection
    {
        $orders = new OrdersLazyCollection($pairId, Order::SIDE_BUY);
        $this->setOffers($pairId, self::TYPE_BIDS, $orders);

        return $orders;
    }

    private function setOffers(int $pairId, string $type, OrdersLazyCollection $offers)
    {
        $this->orders[$pairId][$type] = $offers;
    }

    private function getOrders(int $pairId, string $type): ?OrdersLazyCollection
    {
        return $this->orders[$pairId][$type] ?? null;
    }

    public function add(Order $order): bool
    {
        return $this->takeMakerOrders($order->pair_id, $order->side)->add($order);
    }

    public function remove(Order $order)
    {
        $this->takeMakerOrders($order->pair_id, $order->side)->remove($order->id);
    }

    /**
     * @param Order[] $orders
     */
    public function removeBatch(array $orders)
    {
        if ($orders) {
            $order = current($orders);
            $this->takeMakerOrders($order->pair_id, $order->side)->remove(array_column($orders, 'id'));
        }
    }
}
