<?php
declare(strict_types=1);

namespace App\Services\Crypto\Broadcast;


use App\Services\Crypto\Response\BroadcastResponse;

abstract class AbstractBroadcast
{
    protected string $crypto;
    protected bool $testnet;

    public static function instance(string $crypto, bool $testnet): self
    {
        /** @var self $concrete */
        return app(__NAMESPACE__ . '\\'.static::findConcrete($crypto, $testnet) . 'Broadcast', [
            'crypto' => $crypto,
            'testnet' => $testnet
        ]);
    }

    private static function findConcrete(string $crypto, bool $testnet): string
    {
        $crypto = strtolower($crypto);

        $cryptoConcretes = $testnet ? config('crypto.concrete.broadcast.test') : config('crypto.concrete.broadcast.live');

        foreach($cryptoConcretes as $concrete => $availableCryptos) {
            if (in_array($crypto, $availableCryptos)) {
                return $concrete;
            }
        }

        throw new \Exception("Not found concrete broadcast implementation for $crypto, test: " . (int) $testnet);
    }

    public function __construct(string $crypto, bool $testnet)
    {
        $this->crypto = $crypto;
        $this->testnet = $testnet;
    }

    public abstract function send(string $hex, array $params = []): BroadcastResponse;
}
