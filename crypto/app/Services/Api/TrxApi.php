<?php
declare(strict_types=1);

namespace App\Services\Crypto\Api;


use App\Helpers\Tron;
use Illuminate\Support\Facades\Http;
use mattvb91\TronTrx\Transaction;

class TrxApi extends AbstractApi
{
    const MAINNET_URL = 'https://api.trongrid.io';
    const TESTNET_URL = 'https://api.shasta.trongrid.io';

    protected string $baseUrl;
    protected string $apiKey;

    public function __construct(string $crypto, bool $testnet)
    {
        $this->crypto = $crypto;
        $this->testnet = $testnet;
        $this->apiKey = config('services.trongrid.api_key');
        $this->baseUrl = $testnet ? self::TESTNET_URL : self::MAINNET_URL;

        /*$this->client = new Client([
            'base_uri' => $this->baseUrl,
            'headers' => ['TRON-PRO-API-KEY' => $this->apiKey],
        ]);*/
    }

    public function baseUrl(): string
    {
        return $this->baseUrl;
    }

    private function http(bool $withVersion = false): \Illuminate\Http\Client\PendingRequest
    {
        return Http::withHeaders(['TRON-PRO-API-KEY' => $this->apiKey])
            ->acceptJson()
            ->baseUrl($this->baseUrl() . ($withVersion ? '/v1' : ''));
    }

    private function httpVersion(): \Illuminate\Http\Client\PendingRequest
    {
        return $this->http(true);
    }

    public function accountTransactions(string $address)
    {
        return $this->httpVersion()->get('accounts/' . $address . '/transactions')
            ->json();
    }

    /**
     * @param int $startNum - include in range
     * @param int $endNum - exclude from range
     * @return array|mixed
     */
    public function getBlockByLimitNext(int $startNum, int $endNum): array
    {
        return (array) $this->http()
            ->asJson()
            ->post('wallet/getblockbylimitnext', [
                'startNum' => $startNum,
                'endNum' => $endNum,
            ])
            ->json();
    }

    public function getNowBlock()
    {
        return $this->http()
            ->post('wallet/getnowblock')
            ->json();
    }

    public function getTransactionById(string $txId)
    {
        return $this->http()
            ->asJson()
            ->post('wallet/gettransactionbyid', ['value' => $txId])
            ->json();
    }

    public function latestEvents()
    {
        return $this->http()->get('blocks/latest/events')
            ->json();
    }

    public function contractsEvents(string $address)
    {
        return $this->http()->get('contracts/'.$address.'/events')
            ->json();
    }

    public function triggerSmartContract(float $amount, string $contractAddress, string $fromAddress, string $toAddress): Transaction
    {

        $res = $this->http()
            ->asJson()
            ->post('wallet/triggersmartcontract', [
                'owner_address' => Tron::address2Hex($fromAddress),
                'contract_address' => Tron::address2Hex($contractAddress),
                'function_selector' => 'transfer(address,uint256)',
                'parameter' => Tron::encodeContractParameter($toAddress, $amount),
                'call_value' => 0,
            ])->json();

        if (isset($res['result']['message'])) {
            throw new \Exception($res['result']['code'] . '. ' . $res['result']['message']);
        }

        return new Transaction(
            $res['transaction']['txID'] ?? '',
            json_decode(json_encode($res['transaction']['raw_data'] ?? []))
        );
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

    public function getContract(string $contractAddress)
    {
        return $this->http()->post('wallet/getcontract', [
            'value' => Tron::address2Hex($contractAddress)
        ])->json();
    }
}
