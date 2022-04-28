<?php
declare(strict_types=1);

namespace App\Services\Crypto\Api;


use App\Models\Currency;
use App\Models\Managers\CurrenciesManager;
use App\Traits\Initializer;

abstract class AbstractApi
{
    use Initializer;

    const BALANCE_CACHE = 3600 * 2;

    protected bool $testnet;
    protected string $crypto;

    private ?Currency $currency = null;

    /**
     * Automatically pass $crypto, $testnet and $options params defined at AbstractCryptoFactory::createApi
     * In sub-classes can init for constructor any params in any order or none at all
     */
    //public function __construct(string $crypto, bool $testnet, array $options)

    protected function getCurrency(): Currency
    {
        if (!$this->currency) {
            $this->currency = CurrenciesManager::findByCode((string) $this->crypto, true);
        }

        return $this->currency;
    }

    private static function findConcrete(string $crypto, bool $testnet = false): string
    {
        $crypto = strtolower($crypto);

        foreach(config('crypto.concrete.api.' . ($testnet ? 'test' : 'live')) as $concrete => $availableCryptos) {
            if (in_array($crypto, $availableCryptos)) {
                return $concrete;
            }
        }

        throw new \Exception("Not found concrete implementation for $crypto api");
    }

    public static function instance(string $crypto, $options = []): self
    {
        $testnet = $options['testnet'] ?? false;

        /** @var self $concrete */
        return app(__NAMESPACE__ . '\\'.static::findConcrete($crypto, $testnet) . 'Api', [
            'crypto' => $crypto,
            'testnet' => $testnet,
            'options' => $options
        ]);
    }

    public abstract function pushTransaction(string $hex);

    /**
     * @param string $address
     * @return mixed
     */
    public abstract function addressBalance(string $address);

    /**
     * Total balance on all addresses
     * @return float
     */
    public abstract function retrieveBalance(): float;
}
