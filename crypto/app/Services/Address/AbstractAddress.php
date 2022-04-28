<?php
declare(strict_types=1);

namespace App\Services\Crypto\Address;


use App\Services\Crypto\CryptoAddress;

abstract class AbstractAddress
{
    protected string $crypto;
    protected bool $testnet;

    public static function instance(string $crypto, bool $testnet): self
    {
        return app(__NAMESPACE__ . '\\'.ucfirst(strtolower($crypto)) . 'Address', ['crypto' => $crypto, 'testnet' => $testnet]);
    }

    public function __construct(string $crypto, bool $testnet = false)
    {
        $this->crypto = $crypto;
        $this->testnet = $testnet;
    }

    public function isTestnet(): bool
    {
        return $this->testnet;
    }

    public abstract function generate(): CryptoAddress;
}
