<?php
declare(strict_types=1);

namespace App\Services\Crypto\Transaction;


use App\Models\HotWallet;
use App\Models\Managers\HotWalletManager;
use App\Services\Crypto\{DestinationAddress, Transaction};

abstract class AbstractTransaction
{
    protected HotWallet $hotWallet;
    protected string $crypto;
    protected bool $testnet = false;
    protected ?string $network = null;
    /**
     * @var DestinationAddress[]
     */
    protected array $destinationAddresses = [];
    protected int $feeSatoshi = 0;
    protected bool $extractFee = false;

    public static function instance(string $crypto, bool $testnet, ?string $network = null): self
    {
        $hotWallet = HotWalletManager::findByCode($crypto, $network);
        if (!$hotWallet->id) {
            throw new \Exception("Not found Hot Wallet for $crypto " . ($network ? ' for ' . $network . ' network': ''));
        }

        /** @var self $concrete */
        $concrete = app(__NAMESPACE__ . '\\'.static::findConcrete($hotWallet->currency->mainNetworkCode($network)) . 'Transaction', [
            'hotWallet' => $hotWallet,
            'crypto' => $crypto,
            'testnet' => $testnet,
            'network' => $network,
        ]);

        $concrete->boot();

        return $concrete;
    }

    public function __construct(HotWallet $hotWallet, string $crypto, bool $testnet = false, ?string $network = null)
    {
        $this->hotWallet = $hotWallet;
        $this->crypto = $crypto;
        $this->testnet = $testnet;
        $this->network = $network;
    }

    protected function boot()
    {
    }

    private static function findConcrete(string $crypto): string
    {
        $crypto = strtolower($crypto);

        foreach(config('crypto.concrete.transaction') as $concrete => $availableCryptos) {
            if (in_array($crypto, $availableCryptos)) {
                return $concrete;
            }
        }

        throw new \Exception("Not found concrete implementation for $crypto transaction");
    }

    public function isTestnet(): bool
    {
        return $this->testnet;
    }

    protected function totalTransferAmount(): float
    {
        $amount = 0;
        foreach ($this->destinationAddresses as $address) {
            $amount += $address->getAmount();
        }

        return $amount;
    }

    /**
     * @param DestinationAddress[] $destinationAddresses
     * @param int $feeSatoshi
     * @param bool $extractFee - could be used only if passed one destination address otherwise always false
     * @return Transaction
     */
    public abstract function create(array $destinationAddresses, int $feeSatoshi = 0, bool $extractFee = false): Transaction;

    public function getHotWallet(): HotWallet
    {
        return $this->hotWallet;
    }
}
