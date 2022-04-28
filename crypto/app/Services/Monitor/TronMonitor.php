<?php
declare(strict_types=1);

namespace App\Services\Crypto\Monitor;


use App\Helpers\{Converter, Log, Tron};
use App\Models\{CryptoTransaction, Currency, UserAddress};
use App\Models\Managers\{CurrenciesManager, UserAddressesManager};
use App\Services\Crypto\Api\AbstractApi;
use App\Services\Crypto\Factory\AbstractCryptoFactory;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;


class TronMonitor extends AbstractMonitor
{
    const MAX_BLOCKS_PROCESSED = 100;
    const BLOCK_CACHE_KEY = 'trx_next_from_block_number';
    /**
     * @var \App\Services\Crypto\Api\TrxApi
     */
    private AbstractApi $api;
    private ?Currency $currency;
    private int $currentBlockNumber = 0;
    private int $processBlockNumber = 0;
    private bool $testnet;
    /**
     * @var UserAddress[]
     */
    private array $userAddresses = [];

    public function __construct(string $crypto, int $confirmations, bool $testnet = false)
    {
        parent::__construct($confirmations);

        $this->testnet = $testnet;
        $this->currency = CurrenciesManager::findByCode($crypto);
        if (!$this->currency) {
            throw new \Exception('unknown currency ' . $crypto);
        }

        $this->api = AbstractCryptoFactory::instance($crypto)->createApi();
    }

    public function monitor(?int $blockNumber = null)
    {
        $this->userAddresses = UserAddressesManager::findCurrencyUserAddresses($this->currency->id);
        if (!$this->userAddresses) {
            return ;
        }

        if ($blockNumber) {
            $blocks = $this->pullBlocksRange($blockNumber, $blockNumber + 1);
        } else {
            $blocks = $this->pullBlocks();
        }

        foreach ($blocks as $block) {
            if (empty($block['transactions'])) {
                continue;
            }

            $this->processBlockNumber = (int) $block['block_header']['raw_data']['number'] ?? 0;

            foreach ($block['transactions'] as $transaction) {
                $this->processTransaction($transaction);
            }
        }

        if ((int) date('i') % 3 == 0) {
            $this->checkTransactions();
        }
    }

    public function pullBlocks(): array
    {
        $fromBlockNumber = Cache::get(self::BLOCK_CACHE_KEY);
        $this->currentBlockNumber = (int) Arr::get($this->api->getNowBlock(), 'block_header.raw_data.number');

        if (!$fromBlockNumber) {
            $fromBlockNumber = $this->currentBlockNumber;
        }

        if (!$fromBlockNumber) {
            return [];
        }

        $count = $this->currentBlockNumber - $fromBlockNumber;

        if ($count > self::MAX_BLOCKS_PROCESSED) {
            $toBlockNumber = $fromBlockNumber + self::MAX_BLOCKS_PROCESSED;
        } else {
            $toBlockNumber = $this->currentBlockNumber + 1;
        }

        if ($fromBlockNumber > $toBlockNumber) {
            $toBlockNumber = $fromBlockNumber + 1;
        }

        Cache::put(self::BLOCK_CACHE_KEY, $toBlockNumber, 5 * 60);

        $blocks = $this->api->getBlockByLimitNext((int) $fromBlockNumber, (int) $toBlockNumber)['block'] ?? [];

        Log::trxblock('fromBlockNumber = ' . $fromBlockNumber . ', toBlockNumber = ' . $toBlockNumber . ', blocks = ' . count($blocks));

        return $blocks;
    }

    private function pullBlocksRange(int $fromBlockNumber, int $toBlockNumber): array
    {
        return $this->api->getBlockByLimitNext($fromBlockNumber, $toBlockNumber)['block'] ?? [];
    }

    private function processTransaction(array $transaction)
    {
        foreach (Arr::get($transaction, 'raw_data.contract', []) as $item) {
            $value = Arr::get($item, 'parameter.value', []);

            if (isset($value['owner_address']) && $this->currency->isIgnoredDepositSenderAddress(Tron::hex2Address($value['owner_address']))) {
                continue;
            }

            if ($item['type'] == 'TransferContract') {
                $this->transferContractProcess($transaction, $value);
            } elseif ($item['type'] == 'TriggerSmartContract') {
                $this->triggerSmartContractProcess($transaction, $value);
            }
        }
    }

    private function transferContractProcess(array $transaction, array $value)
    {
        if (!isset($value['amount']) || (float) $value['amount'] == 0) {
            return;
        }

        $address = Tron::hex2Address($value['to_address']);
        if (!isset($this->userAddresses[$address])) {
            return;
        }

        $this->createTransaction($transaction, $address, (int) $this->userAddresses[$address], $value['amount']);
    }

    private function triggerSmartContractProcess(array $transaction, array $value)
    {
        if (!isset($value['data']) || !isset($value['contract_address'])) {
            return;
        }

        $assetData = Tron::decodeContractData($value['data']);
        if (!isset($this->userAddresses[$assetData['address']])) {
            return;
        }

        $contracts = CurrenciesManager::fetchTrc20Contracts();
        $contractAddress = Tron::hex2Address($value['contract_address']);
        if (!isset($contracts[$contractAddress])) {
            return ;
        }

        $this->createTransaction($transaction,
            $assetData['address'],
            (int) $this->userAddresses[$assetData['address']],
            $assetData['amount'],
            (int) $contracts[$contractAddress]);
    }

    private function createTransaction(array $transaction, string $address, int $userId, $amount, int $tokenId = 0)
    {
        CryptoTransaction::query()->firstOrCreate([
            'currency_id' => $this->currency->id,
            'txid' => $transaction['txID']
        ], [
            'amount' => Converter::coinToValue($amount, $this->currency->tx_decimals),
            'address' => $address,
            'category' => 'receive',
            'user_id' => $userId,
            'status' => CryptoTransaction::STATUS_NOT_CONFIRMED,
            'response' => ['block_number' => $this->processBlockNumber],
            'token_id' => $tokenId
        ]);
    }

    private function checkTransactions()
    {
        /** @var CryptoTransaction[]|Collection $transactions */
        $transactions = CryptoTransaction::query()
            ->where('currency_id', $this->currency->id)
            ->where('status', CryptoTransaction::STATUS_NOT_CONFIRMED)
            ->get();

        if ($transactions->isEmpty()) {
            return ;
        }

        foreach ($transactions as $transaction) {
            $tx = $this->api->getTransactionById($transaction->txid);
            if (!$tx || Arr::get($tx, 'ret.0.contractRet') != 'SUCCESS') {
                continue;
            }

            $txBlockNumber = $transaction->response['block_number'] ?? 0;

            if (($this->currentBlockNumber - $txBlockNumber) < $this->confirmations || !$transaction->isReceive()) {
                return ;
            }

            $userAddress = UserAddressesManager::findByUserAndCurrency($transaction->user_id, $transaction->currency_id);
            if (!$userAddress || !$userAddress->isDeposit()) {
                return ;
            }

            $transaction->response = array_merge($tx, ['block_number' => $txBlockNumber]);
            $transaction->confirmed();
            $transaction->save();

            $network = null;
            $changeBalance = true;
            $usingUserAddress = $userAddress;

            if ($transaction->isTrc20()) {
                $network = Currency::NETWORK_TRC20;
                $changeBalance = false;
                $usingUserAddress = new UserAddress([
                    'currency_id' => $transaction->token_id,
                    'address' => $transaction->address,
                    'user_id' => $transaction->user_id,
                ]);
            }

            $this->depositOperation($usingUserAddress, $transaction->amount, $changeBalance, $transaction->id, $network);
        }
    }
}
