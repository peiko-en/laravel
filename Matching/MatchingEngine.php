<?php
declare(strict_types=1);

namespace App\Services\Matching;


use App\Models\Order;

class MatchingEngine
{
    private Trading $trading;

    public function __construct(Trading $trading)
    {
        $this->trading = $trading;
    }

    public function run(Order $order)
    {
        $this->trading->trade($order);
    }

    public function cancel(Order $order)
    {
        $this->trading->cancel($order);
    }

    public function removeFromOrderBook(Order $order)
    {
        $this->trading->removeFromOrderBook($order);
    }

    /**
     * @param Order[] $orders
     */
    public function removeCollectionFromOrderBook(array $orders)
    {
        $this->trading->removeBatchFromOrderBook($orders);
    }

    public function orderBookChangeEvent(string $pair)
    {
        $this->trading->orderBookChangeEvent($pair);
    }
}
