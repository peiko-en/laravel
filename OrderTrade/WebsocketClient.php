<?php

declare(strict_types=1);

namespace App\Services;

use App\Infrastructure\Traits\Initializer;
use Ratchet\Client\Connector as RatchetConnector;
use Ratchet\Client\WebSocket;
use Ratchet\RFC6455\Messaging\MessageInterface;
use React\EventLoop\Loop;
use React\Socket\Connector as ReactConnector;

class WebsocketClient
{
    use Initializer;

    private int $startTime;
    private string $url;
    private int $duration;
    private $callback;
    private bool $ping = false;
    private int $pingInterval = 60;
    private string $pingType = 'op';
    private array $subscribe = [];

    public function __construct(string $url, int $duration, callable $callback, array $params = [])
    {
        $this->startTime = time();
        $this->url = $url;
        $this->duration = $duration;
        $this->callback = $callback;
        $this->configure($params);
    }

    public function connect()
    {
        $loop = Loop::get();
        $react = new ReactConnector($loop);
        $connector = new RatchetConnector($loop, $react);

        $connector($this->url)
            ->then(function (WebSocket $conn) use ($loop) {
                $conn->on('message', function (MessageInterface $msg) use ($conn, $loop) {
                    call_user_func($this->callback, json_decode((string)$msg, true));
                });

                $this->ping($loop, $conn);
                $this->subscribe($conn);
                $this->closeConnection($loop, $conn);
            }, function (\Exception $e) use ($loop) {
                logger($e->getMessage());
                $loop->stop();
            });
        $loop->run();
    }

    private function ping($loop, $conn)
    {
        if ($this->ping) {
            $loop->addPeriodicTimer($this->pingInterval, function () use ($conn) {
                $conn->send($this->getPingTextByType($this->pingType));
            });
        }
    }

    private function closeConnection($loop, $conn)
    {
        $loop->addPeriodicTimer(5, function () use ($loop, $conn) {
            if ($this->getConnectionTime() >= $this->duration) {
                $conn->close();
                $loop->stop();
            }
        });
    }

    private function getConnectionTime(): int
    {
        return time() - $this->startTime;
    }

    private function getPingTextByType($type = 'op'): string
    {
        return $this->pingTextTypes()[$type];
    }

    private function pingTextTypes(): array
    {
        return [
            'op' => '{"op":"ping"}',
            'id' => '{"id":"' . time() . '","type":"ping"}',
        ];
    }

    public function subscribe($conn)
    {
        if ($this->subscribe) {
            foreach ($this->subscribe as $subscribe) {
                $conn->send(json_encode($subscribe));
            }
        }
    }
}
