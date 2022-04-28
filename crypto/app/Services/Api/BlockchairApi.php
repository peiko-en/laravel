<?php
declare(strict_types=1);

namespace App\Services\Crypto\Api;


use App\Helpers\Math;
use App\Models\Currency;
use App\Models\Managers\UserAddressesManager;
use App\Services\Crypto\AddressUtxo;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class BlockchairApi extends AbstractApi implements WithAddressData
{
    const BASE_URL = 'https://api.blockchair.com/';
    const NETWORK_BTC = 'bitcoin';
    const NETWORK_LTC = 'litecoin';
    const NETWORK_DASH = 'dash';
    const NETWORK_ZCASH = 'zcash';
    const NETWORK_DOGECOIN = 'dogecoin';
    const NETWORK_RIPPLE = 'ripple';
    const NETWORK_BITCOIN_CASH = 'bitcoin-cash';

    protected string $network;
    protected string $apiKey;

    public static function make(string $crypto, bool $testnet = false): self
    {
        return app(BlockchairApi::class, ['crypto' => $crypto, 'testnet' => $testnet]);
    }

    public function __construct(string $crypto, bool $testnet = false, array $options = [])
    {
        $this->crypto = strtoupper($crypto);
        $this->testnet = $testnet;
        $this->apiKey = (string) config('services.blockchair.api_key');
        $this->configure($options);

        $this->defineNetwork();
    }

    protected function defineNetwork()
    {
        if ($this->crypto == Currency::BTC_CODE) {
            $this->setNetwork($this->testnet ? self::NETWORK_BTC . '/testnet' : self::NETWORK_BTC);
        } elseif ($this->crypto == Currency::LTC_CODE) {
            $this->setNetwork(self::NETWORK_LTC);
        } elseif ($this->crypto == Currency::DASH_CODE) {
            $this->setNetwork(self::NETWORK_DASH);
        } elseif ($this->crypto == Currency::ZEC_CODE) {
            $this->setNetwork(self::NETWORK_ZCASH);
        } elseif ($this->crypto == Currency::DOGE_CODE) {
            $this->setNetwork(self::NETWORK_DOGECOIN);
        } elseif ($this->crypto == Currency::XRP_CODE) {
            $this->setNetwork(self::NETWORK_RIPPLE);
        } elseif ($this->crypto == Currency::BCH_CODE) {
            $this->setNetwork(self::NETWORK_BITCOIN_CASH);
        } else {
            throw new \Exception('Unknown crypto ' . $this->crypto . ' blockchair network');
        }
    }

    protected function setNetwork(string $network)
    {
        $this->network = $network;
    }

    public function getNetwork(): string
    {
        return $this->network;
    }

    protected function createUrl(string $uri, array $params = []): string
    {
        $request = '';

        if ($this->apiKey) {
            $params['key'] = $this->apiKey;
        }

        if ($params) {
            $request .= '?' . http_build_query($params);
        }

        return self::BASE_URL . $this->network . '/' . $uri . $request;
    }

    public function addresses(array $addresses, bool $details = false)
    {
        $params = [];

        if ($details) {
            $params = ['transaction_details' => true];
        }

        return Http::get($this->createUrl('dashboards/addresses/'. implode(',', $addresses), $params))->json();
    }

    public function addressData(string $address): AddressUtxo
    {
        $addressData = new AddressUtxo($address);

        try {
            $res = Http::get($this->createUrl('dashboards/address/' . $address))->json();

            $data = $res['data'][$address] ?? null;
            if ($data) {
                foreach ($data['utxo'] as $out) {
                    $addressData->addUtxo($out['transaction_hash'], $out['index'], $out['value'], $out['block_id']);
                }
            }
        } catch (\Exception $e) {
            logger($e);
        }

        return $addressData;
    }

    public function pushTransaction(string $hex)
    {
        $res = Http::asJson()->post($this->createUrl('push/transaction'), ['data' => $hex]);

        if ($res->failed()) {
            throw new \Exception($res->json('context.error'));
        }

        return true;
    }

    public function addressBalance(string $address)
    {
        $res = $this->addresses([$address]);
        $balance = $res['data']['addresses'][$address]['balance'] ?? 0;
        return Math::div($balance, 10 ** $this->getCurrency()->txDecimals());
    }

    public function retrieveBalance(): float
    {
        return Cache::remember('live_balance_' . $this->getCurrency()->code, 24 * 3600, function () {
            try {
                $addresses = UserAddressesManager::findCurrencyListAddresses($this->getCurrency()->id);
                $result = Http::get($this->createUrl('addresses/balances', [
                    'addresses' => implode(',', $addresses)
                ]));

                if ($result->failed()) {
                    throw new \Exception($result->json('context.error'));
                }

                $data = $result->json()['data'] ?? [];
                if ($data) {
                    return (float) Math::div(array_sum($data), 10 ** $this->getCurrency()->txDecimals());
                }
            } catch (\Exception $e) {
                logger('BlockchairApi: ' . $e->getMessage());
            }

            return 0;
        });
    }

    public function stats(): array
    {
        $res = Http::get($this->createUrl('stats'));
        if ($res->failed()) {
            throw new \Exception('Request status  code: '. $res->status() . '. Response body: ' . $res->body());
        }

        return $res->json();
    }
}
