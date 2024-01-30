<?php
declare(strict_types=1);

namespace App\Services\Matching;

use App\Helpers\{Log, Math, RedisHelper};
use App\Models\{Managers\MatchingOrderManager, MatchAskOrder, MatchBidOrder, Order};
use Illuminate\Redis\Connections\Connection;

class OrdersLazyCollection implements \Iterator
{
    const LOAD_SIZE = 50;
    const STORAGE_CHUNK_SIZE = 2000;

    /**
     * @var Order[]
     */
    private array $items = [];
    private int $side;
    private int $pairId;

    private int $position = 0;
    /**
     * @var \Illuminate\Redis\Connections\PhpRedisConnection
     */
    private Connection $redis;
    private string $storageKey;

    public function __construct(int $pairId, int $side)
    {
        $this->pairId = $pairId;
        $this->side = $side;
        $this->redis = RedisHelper::matching();
        $this->storageKey = 'orders' . $pairId . $side;
    }

    public function current()
    {
        return $this->items[$this->position];
    }

    public function next()
    {
        $this->position++;

        if (!$this->valid()) {
            $this->load();
        }
    }

    public function key()
    {
        return $this->position;
    }

    /**
     * Calling ones before cycle  is run
     */
    public function rewind()
    {
        $this->items = array_values($this->items);
        $this->position = 0;
    }

    public function valid()
    {
        return isset($this->getItems()[$this->position]);
    }

    private function getItems(): array
    {
        if (!$this->items) {
            $this->load();
        }

        return $this->items;
    }

    private function load($tries = 0)
    {
        $this->items = [];
        $this->position = 0;
        $ids = $this->loadIdsFromStorage();

        if ($ids) {
            $disorderedOrders = Order::query()
                ->whereIn('id', $ids)
                ->get()
                ->keyBy('id')
                ->all();

            foreach ($ids as $id) {
                if (isset($disorderedOrders[$id])) {
                    $this->items[] = $disorderedOrders[$id];
                } else {
                    $this->redis->zrem($this->storageKey, $id);
                    $this->removeFromDb($id);
                }
            }

            if (!$this->items && $tries < 3) {
                $this->load($tries + 1);
            }
        }
    }

    private function isBuy(): bool
    {
        return $this->side == Order::SIDE_BUY;
    }

    private function isSell(): bool
    {
        return $this->side == Order::SIDE_SELL;
    }

    public function loadIdsFromStorage($size = self::LOAD_SIZE): array
    {
        $ids = [];

        try {
            $ids = $this->retrieveOrderIds($size);
        } catch (\Exception $e) {
            Log::matching($e);
        }

        if (!$ids) {
            $ids = $this->updateStorage();
        }

        return $ids;
    }

    public function retrieveOrderIds(int $size): array
    {
        return $this->isBuy() ? $this->retrieveBidIds($size) : $this->retrieveAskIds($size);
    }

    private function retrieveBidIds(int $size): array
    {
        //We get item in reverse direction,
        // so last element in same score price group have wrong position after reversing
        $res = $this->redis->zrevrange($this->storageKey, 0, $size, true);
        if (!$res) {
            return [];
        }

        //if no same prices we needn't to re-sorting
        if (count(array_unique($res)) == count($res)) {
            return array_keys($res);
        }

        //preparing data to more convenient structure for sorting
        $interimItems = [];
        foreach ($res as $id => $price) {
            $interimItems[] = ['id' => $id, 'price' => $price];
        }

        usort($interimItems, function($arr1, $arr2) {
            $res = Math::comp($arr2['price'], $arr1['price']);
            if ($res == 0) {
                return $arr1['id'] <=> $arr2['id'];
            }

            return $res;
        });

        return array_column($interimItems, 'id');
    }

    private function retrieveAskIds(int $size): array
    {
        return $this->redis->zrange($this->storageKey, 0, $size);
    }

    private function updateStorage(): array
    {
        $ids = [];
        $orders = MatchingOrderManager::fetchOrderIds($this->pairId, $this->side, self::STORAGE_CHUNK_SIZE);

        foreach ($orders as $order) {
            $this->set((float) $order['price'], (int) $order['order_id']);
            $ids[] = $order['order_id'];
        }

        return $ids;
    }

    private function set(float $price, int $id)
    {
        $this->redis->zadd($this->storageKey, $price, $id);
    }

    public function add(Order $order): bool
    {
        $this->addToDb($order);
        $this->addToStorage((float) $order->price, (int) $order->id);

        //no necessary to add if items array is empty
        if (!$this->items) {
            return true;
        }

        $this->items[] = $order;

        usort($this->items, function(Order $o1, Order $o2) {
            if ($o1->isBuy()) {
                $res = $o2->price <=> $o1->price;
            } else {
                $res = $o1->price <=> $o2->price;
            }

            if ($res != 0) {
                return $res;
            }

            return $o1->id <=> $o2->id;
        });

        //if new element set last in array we must remove from array because
        //it could have price less or greater than orders which have not loaded yet
        if ($this->items[array_key_last($this->items)]->id == $order->id) {
            array_pop($this->items);
        }

        return true;
    }

    /**
     * @param array|int $id
     */
    public function remove($id)
    {
        if (!is_array($id)) {
            $id = [$id];
        }

        $this->redis->zrem($this->storageKey, ...$id);
        $this->removeFromDb($id);

        $removedKey = -1;
        foreach ($this->items as $key => $order) {
            if (in_array($order->id, $id)) {
                unset($this->items[$key]);

                if ($removedKey == -1) {
                    $removedKey = $key;
                }
            }
        }

        //create new items array with ordered keys after removed
        if ($removedKey > $this->position) {
            $copyItems = [];
            $key = key($this->items);
            foreach ($this->items as $item) {
                $copyItems[$key++] = $item;
            }

            $this->items = $copyItems;
        }
    }

    private function addToStorage(float $price, int $id)
    {
        if ($this->isBuy()) {
            $idPrice = $this->redis->zrange($this->storageKey, 0, 0, true);
        } else {
            $idPrice = $this->redis->zrange($this->storageKey, -1, -1, true);
        }

        if (!$idPrice) {
            return ;
        }

        $lastPrice = (float) current($idPrice);
        if ($this->isBuy() && $price > $lastPrice) {
            $this->set($price, $id);
        } elseif ($this->isSell() && $price < $lastPrice) {
            $this->set($price, $id);
        }
    }

    private function addToDb(Order $order)
    {
        MatchingOrderManager::orderQuery($order->side)->create([
            'order_id' => $order->id,
            'pair_id' => $order->pair_id,
            'price' => $order->price,
        ]);
    }

    /**
     * @param array|int $orderId
     */
    private function removeFromDb($orderId)
    {
        if (!is_array($orderId)) {
            $orderId = [$orderId];
        }

        MatchingOrderManager::orderQuery($this->side)
            ->whereIn('order_id', $orderId)
            ->delete();
    }
}
