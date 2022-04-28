<?php
declare(strict_types=1);

namespace App\Services\Crypto\Transaction;


use App\Helpers\Tron;
use App\Models\Currency;
use App\Services\Crypto\{Api\AbstractApi, Api\TrxApi, DestinationAddress, Factory\AbstractCryptoFactory, Transaction};
use mattvb91\TronTrx\{Address, Support\Secp};
use App\Helpers\Converter;
use App\Helpers\DIHelper;

class TronTransaction extends AbstractTransaction
{
    /**
     * @var TrxApi
     */
    private AbstractApi $api;

    protected function boot()
    {
        $this->api = AbstractCryptoFactory::instance(Currency::TRX_CODE)->createApi();
    }
    /**
     * @param DestinationAddress[] $destinationAddresses
     * @param int $fee
     * @param bool $extractFee
     * @return Transaction
     */
    public function create(array $destinationAddresses, int $fee = 0, bool $extractFee = false): Transaction
    {
        $destinationAddress = current($destinationAddresses);

        $toAddress = new Address(
            $destinationAddress->getAddress(),
            '',
            Tron::address2Hex($destinationAddress->getAddress())
        );

        $fromAddress = new Address(
            $this->hotWallet->address,
            $this->hotWallet->private_key,
            Tron::address2Hex($this->hotWallet->address)
        );

        $amount = (float) Converter::valueToCoin($destinationAddress->getAmount(), $this->hotWallet->txDecimals());

        if ($this->hotWallet->isToken()) {
            $transaction = $this->api->triggerSmartContract($amount, $this->hotWallet->contractAddress(), $fromAddress->address, $toAddress->address);
        } else {
            $transaction = DIHelper::tronWallet()->createTransaction($toAddress, $fromAddress, $amount);
        }

        $signature = Secp::sign($transaction->txID, $fromAddress->privateKey);
        $transaction->signature[] = $signature;

        return (new Transaction($transaction->txID, $signature))->setParams([
            'rawTransaction' => $transaction
        ]);
    }
}
