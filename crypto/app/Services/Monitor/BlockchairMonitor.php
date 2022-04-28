<?php
declare(strict_types=1);

namespace App\Services\Crypto\Monitor;


use App\Helpers\Converter;
use App\Helpers\Log;
use App\Models\{CryptoTransaction, Currency, UserAddress};
use App\Models\Managers\{CurrenciesManager, UserAddressesManager};
use App\Services\Crypto\Api\BlockchairApi;


class BlockchairMonitor extends AbstractMonitor
{
    const MAX_PER_REQUEST = 100;

    private BlockchairApi $api;
    private ?Currency $currency;

    public function __construct(string $crypto, int $confirmations, bool $testnet = false)
    {
        parent::__construct($confirmations);

        $this->currency = CurrenciesManager::findByCode($crypto);
        if (!$this->currency) {
            throw new \Exception('unknown currency ' . $crypto);
        }

        $this->api = BlockchairApi::make($crypto, $testnet);
    }

    public function monitor()
    {
        $userAddresses = UserAddressesManager::findCurrencyAddressesOfActiveUsers($this->currency->id);
        if (!$userAddresses) {
            return ;
        }

        if ($this->currency->isBch()) {
            $userAddresses = $this->reindexBchUserAddresses($userAddresses);
        }

        //blocks - total blocks include zero block.
        $totalBlocks = $this->api->stats()['data']['blocks'] ?? 0;
        if (!$totalBlocks) {
            Log::crypto('Cant defines last block id for ' . $this->currency->code);
        }

        $lastBlockId = $totalBlocks - 1;

        foreach (array_chunk(array_keys($userAddresses), self::MAX_PER_REQUEST) as $chunkAddresses) {
            $res = $this->api->addresses($chunkAddresses, true);
            $addresses = $res['data']['addresses'] ?? [];
            $transactions = collect($res['data']['transactions'] ?? [])->groupBy('address');

            foreach ($addresses as $address => $props) {
                $userAddress = $userAddresses[$address] ?? null;
                if ($userAddress) {
                    $balance = Converter::satoshiToBtc($props['balance']);

                    if ($userAddress->approx_balance != $balance) {
                        $userAddress->approx_balance = $balance;
                        $userAddress->save();
                    }

                    foreach ($transactions->get($address, []) as $transaction) {
                        $isConfirmed = ($lastBlockId - $transaction['block_id']) >= $this->confirmations;

                        if ($transaction['balance_change'] > 0 && $isConfirmed && !$this->isExistsTransaction($this->currency->id, $transaction['hash'])) {
                            $depositAmount = Converter::satoshiToBtc($transaction['balance_change']);
                            $cryptoTransaction = $this->createTransaction($transaction, $address, $userAddress->user_id, $depositAmount);

                            if ($cryptoTransaction->id) {
                                $this->depositOperation($userAddress, $depositAmount, false, $cryptoTransaction->id);
                            }
                        }
                    }
                }
            }
        }
    }

    /**
     * @param array $transaction
     * @param string $address
     * @param int $userId
     * @param $amount
     * @return object
     */
    protected function createTransaction(array $transaction, string $address, int $userId, $amount): CryptoTransaction
    {
        return CryptoTransaction::query()->create([
            'currency_id' => $this->currency->id,
            'txid' => $transaction['hash'],
            'amount' => $amount,
            'address' => $address,
            'category' => 'receive',
            'user_id' => $userId,
            'response' => $transaction,
            'status' => CryptoTransaction::STATUS_CONFIRMED,
        ]);
    }

    public function reindexBchUserAddresses($userAddresses): array
    {
        return collect($userAddresses)
            ->keyBy(fn(UserAddress $userAddress) => str_replace(['bitcoincash:', 'bchtest:'], '', $userAddress->address))
            ->all();
    }
}
