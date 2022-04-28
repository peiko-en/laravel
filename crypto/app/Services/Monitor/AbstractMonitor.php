<?php

namespace App\Services\Crypto\Monitor;


use App\Models\CryptoTransaction;
use App\Models\Deposit;
use App\Models\Transaction;
use App\Models\UserAddress;
use App\Services\Payments\Operations\DepositOperation;

abstract class AbstractMonitor
{
    protected int $confirmations;

    public function __construct(int $confirmations)
    {
        $this->confirmations = $confirmations;
    }

    public function collect(?string $hash = null)
    {
    }

    public abstract function monitor();

    protected function depositOperation(UserAddress $userAddress, $amount, $changeBalance = true, $cryptoTxId = 0, $network = null)
    {
        /** @var Deposit $deposit */
        $deposit = Deposit::query()->newModelInstance([
            'user_id' => $userAddress->user_id,
            'amount' => $amount,
            'currency_id' => $userAddress->currency_id,
            'crypto_address' => $userAddress->address,
            'crypto_tx_id' => (int) $cryptoTxId,
            'network' => $network,
        ]);

        if ($changeBalance) {
            $userAddress->approx_balance += $amount;
            $userAddress->save();
        }

        $deposit->calcAndFillFee();

        (new DepositOperation(new Transaction))->confirmDeposit($deposit);
    }

    protected function isExistsTransaction(int $currencyId, string $txId): bool
    {
        return CryptoTransaction::query()->where(['currency_id' => $currencyId, 'txid' => $txId])->exists();
    }
}
