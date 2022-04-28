<?php
declare(strict_types=1);

namespace App\Services\Crypto\Factory;

use App\Models\Currency;
use App\Services\Crypto\Address\AbstractAddress;
use App\Services\Crypto\Api\AbstractApi;
use App\Services\Crypto\Broadcast\AbstractBroadcast;
use App\Services\Crypto\CryptoAddress;
use App\Services\Crypto\Monitor\AbstractMonitor;
use App\Services\Crypto\Transaction\AbstractTransaction;

abstract class AbstractCryptoFactory
{
    protected string $crypto;
    protected string $token = '';
    protected bool $testnet = false;
    protected int $confirmations = 1;
    protected ?Currency $currency = null;
    protected ?string $network = null;

    public static function instance(string $crypto, array $options = []): AbstractCryptoFactory
    {
        $crypto = strtolower($crypto);
        $options = array_merge($options,
            config('payments.' . $crypto, []),
            ['crypto' => $crypto]
        );

        $namespace = __NAMESPACE__.'\\'.ucfirst(strtolower($crypto)) . 'Factory';

        return new $namespace($options);
    }

    public static function instanceByCurrency(Currency $currency, array $options = []): AbstractCryptoFactory
    {
        $crypto = $currency->mainNetworkCode($options['network'] ?? null);
        $options['currency'] = $currency;

        if ($currency->isToken()) {
            $options['token'] = strtolower($currency->code);
        }

        return static::instance($crypto, $options);
    }

    public function __construct(array $options = [])
    {
        foreach ($options as $key => $value) {
            if (property_exists($this, $key)) {
                $this->$key = $value;
            }
        }
    }

    public function generateAddress(): CryptoAddress
    {
        return $this->createAddress()->generate();
    }

    public function createAddress(): AbstractAddress
    {
        return AbstractAddress::instance($this->crypto, $this->testnet);
    }

    public function createTransaction(): AbstractTransaction
    {
        return AbstractTransaction::instance($this->token ?: $this->crypto, $this->testnet, $this->network);
    }

    public function createBroadcast(): AbstractBroadcast
    {
        return AbstractBroadcast::instance($this->crypto, $this->testnet);
    }

    public abstract function createMonitor(): AbstractMonitor;

    public function createApi(): AbstractApi
    {
        return AbstractApi::instance($this->crypto, [
            'testnet' => $this->testnet,
            'token' => $this->token,
            'currency' => $this->currency,
        ]);
    }

    public function createTxUrl(string $txId): string
    {
        return '';
    }
}
