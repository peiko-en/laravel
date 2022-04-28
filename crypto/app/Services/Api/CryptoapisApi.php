<?php
declare(strict_types=1);

namespace App\Services\Crypto\Api;


use App\Helpers\Converter;
use App\Models\Currency;
use App\Services\Crypto\AddressUtxo;
use Illuminate\Support\Facades\Http;

class CryptoapisApi extends AbstractApi implements WithAddressData
{
    const BASE_URL = 'https://rest.cryptoapis.io/v2';

    private array $blockchains = [
        Currency::BTC_CODE => 'bitcoin',
        Currency::LTC_CODE => 'litecoin',
        Currency::BCH_CODE => 'bitcoin-cash',
        Currency::DOGE_CODE => 'dogecoin',
        Currency::DASH_CODE => 'dash',
        Currency::ZEC_CODE => 'zcash',
    ];

    private string $token = '';

    public function __construct(string $crypto, bool $testnet = false)
    {
        $this->crypto = $crypto;
        $this->testnet = $testnet;
        $this->token = (string) config('services.cryptoapis.api_key');
    }

    public function pushTransaction(string $hex)
    {
    }

    public function addressBalance(string $address)
    {
        return 0;
    }

    public function retrieveBalance(): float
    {
        return 0;
    }

    public function addressData(string $address): AddressUtxo
    {
        $addressData = new AddressUtxo($address);

        try {
            $res = $this->http()
                ->get($this->createDataUrl('addresses/' . $address . '/unspent'))
                ->json();

            foreach (data_get($res, 'data.items', []) as $item) {
                if (!isset($item['vout'])) {
                    continue;
                }

                foreach ($item['vout'] as $out) {
                    if (!$out['isSpent']) {
                        $addressData->addUtxo($item['transactionHash'], $item['index'], (int) Converter::valueToCoin($out['value'], 8), $item['minedInBlockHeight']);
                    }
                }
            }
        } catch (\Exception $e) {
            logger($e);
        }

        return $addressData;
    }

    private function http(): \Illuminate\Http\Client\PendingRequest
    {
        return Http::withHeaders(['X-API-Key' => $this->token])->asJson();
    }

    private function createDataUrl(string $uri, array $params = []): string
    {
        return $this->createUrl('blockchain-data', $uri, $params);
    }

    private function createToolsUrl(string $uri, array $params = []): string
    {
        return $this->createUrl('blockchain-tools', $uri, $params);
    }

    private function createUrl(string $section, string $uri, array $params = []): string
    {
        $request = '';
        if ($params) {
            $request .= '?' . http_build_query($params);
        }

        return self::BASE_URL . '/' . $section . '/' . $this->getBlockchain() . '/' .$this->getNetwork() . '/' . $uri . $request;
    }

    private function getBlockchain(): string
    {
        return $this->blockchains[strtoupper($this->crypto)] ?? '';
    }

    private function getNetwork(): string
    {
        return $this->testnet ? 'testnet' : 'mainnet';
    }

    public function broadcastTx(string $signedTxHash)
    {
        return $this->http()->post($this->createToolsUrl('transactions/broadcast'), [
            'context' => '',
            'data' => [
                'item' => [
                    'callbackSecretKey' => '',
                    'callbackUrl' => '',
                    'signedTransactionHex' => $signedTxHash,
                ]
            ]
        ])->json();
    }
}
