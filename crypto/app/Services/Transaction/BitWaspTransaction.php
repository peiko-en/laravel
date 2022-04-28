<?php
declare(strict_types=1);

namespace App\Services\Crypto\Transaction;


use App\Helpers\Converter;
use App\Services\Crypto\AddressUtxo;
use App\Services\Crypto\Api\AbstractApi;
use App\Services\Crypto\Api\WithAddressData;
use App\Services\Crypto\BitWaspNetworkFactory;
use App\Services\Crypto\DestinationAddress;
use App\Services\Crypto\Transaction;
use App\Services\Crypto\UtxOutput;

use BitWasp\Bitcoin\Address\AddressCreator;
use BitWasp\Bitcoin\Bitcoin;
use BitWasp\Bitcoin\Key\Factory\PrivateKeyFactory;
use BitWasp\Bitcoin\Script\ScriptFactory;
use BitWasp\Bitcoin\Transaction\Factory\Signer;
use BitWasp\Bitcoin\Transaction\TransactionFactory;
use BitWasp\Bitcoin\Transaction\TransactionInterface;
use BitWasp\Bitcoin\Transaction\TransactionOutput;

class BitWaspTransaction extends AbstractTransaction
{
    const FEE_SATOSHI_PER_BYTE = 3;
    const MAX_FEE_SATOSHI = 5000;

    /**
     * @var WithAddressData
     */
    private AbstractApi $api;

    protected function boot()
    {
        $this->crypto = strtolower($this->crypto);
        Bitcoin::setNetwork(BitWaspNetworkFactory::getNetwork($this->crypto, $this->testnet));

        $this->api = AbstractApi::instance($this->crypto, ['testnet' => $this->testnet]);
    }

    /**
     * @param DestinationAddress[] $destinationAddresses
     * @param bool $extractFee
     * @param int $feeSatoshi
     * @return Transaction
     * @throws \BitWasp\Bitcoin\Exceptions\UnrecognizedAddressException
     */
    public function create(array $destinationAddresses, int $feeSatoshi = 0, bool $extractFee = false): Transaction
    {
        $this->destinationAddresses = $destinationAddresses;
        $this->feeSatoshi = $feeSatoshi;

        if (count($this->destinationAddresses) == 1) {
            $this->extractFee = $extractFee;
        }

        $addressUtxo = $this->api->addressData($this->hotWallet->address);
        $transferAmount = Converter::btcToSatoshi($this->totalTransferAmount());

        $utxos = $this->takeUtxo($addressUtxo, $transferAmount);

        $transaction = $this->buildTransaction($utxos);

        return $this->sign($transaction, $utxos);
    }

    /**
     * @param AddressUtxo $addressUtxo
     * @param int $transferAmount
     * @return UtxOutput[]
     * @throws \Exception
     */
    private function takeUtxo(AddressUtxo $addressUtxo, int $transferAmount): array
    {
        if (!$this->extractFee && $this->feeSatoshi > 0) {
            $subtotalAmount = $transferAmount + $this->feeSatoshi;
            $utxos = $addressUtxo->suitableOutputs($subtotalAmount);
        } else {
            $subtotalAmount = $transferAmount;
            $utxos = $addressUtxo->suitableOutputs($transferAmount);

            while ($utxos) {
                $this->feeSatoshi = $this->calculateFee($utxos);
                $subtotalAmount = $transferAmount;

                if ($this->summarizeUtxoValue($utxos) >= $subtotalAmount) {
                    break;
                }

                $utxos = $addressUtxo->suitableOutputs($subtotalAmount);
            }
        }

        if (!$utxos) {
            throw new \Exception(trans('app.no-coins-for-tx', [
                'available' => Converter::satoshiToBtc($addressUtxo->getBalance()),
                'need' => Converter::satoshiToBtc($subtotalAmount),
            ]));
        }

        return $utxos;
    }

    private function summarizeUtxoValue(array $utxos): int
    {
        return array_sum(array_column($utxos, 'value'));
    }

    /**
     * @param UtxOutput[] $utxos
     * @throws \BitWasp\Bitcoin\Exceptions\UnrecognizedAddressException
     */
    private function calculateFee(array $utxos): int
    {
        $transaction = $this->buildTransaction($utxos);
        $preliminaryTx = $this->sign($transaction, $utxos);

        $feeSatoshi = (int) ceil($preliminaryTx->getSize() * self::FEE_SATOSHI_PER_BYTE);
        if ($feeSatoshi > self::MAX_FEE_SATOSHI) {
            $feeSatoshi = self::MAX_FEE_SATOSHI;
        }

        return $feeSatoshi;
    }

    /**
     * @param UtxOutput[] $utxos
     * @return TransactionInterface
     * @throws \BitWasp\Bitcoin\Exceptions\UnrecognizedAddressException
     */
    private function buildTransaction(array $utxos): TransactionInterface
    {
        $transferAmount = Converter::btcToSatoshi($this->totalTransferAmount());
        if (!$utxos) {
            throw new \Exception(trans('app.no-coins-for-tx'));
        }

        $transaction = TransactionFactory::build();

        if ($this->hotWallet->currency->isBch()) {
            $addressCreator = new \App\Services\Crypto\Utils\BitcoinCash\AddressCreator();
        } else {
            $addressCreator = new AddressCreator();
        }

        $totalInputAmount = 0 ;
        foreach ($utxos as $utxo) {
            $transaction->input($utxo->hash, $utxo->index);
            $totalInputAmount += $utxo->value;
        }

        //main outputs
        foreach ($this->destinationAddresses as $destAddress) {
            $amount = $destAddress->amountInSatoshi();
            if ($this->extractFee) {
                $amount -= $this->feeSatoshi;
            }

            $transaction->payToAddress($amount, $addressCreator->fromString($destAddress->getAddress()));
        }

        $changeAmount = $totalInputAmount - $transferAmount;

        if (!$this->extractFee) {
            $changeAmount -= $this->feeSatoshi;
        }

        if ($changeAmount > 0) {
            //output for change
            $transaction->payToAddress($changeAmount, $addressCreator->fromString($this->hotWallet->address));
        }

        return $transaction->get();
    }

    /**
     * @param TransactionInterface $transaction
     * @param array|UtxOutput[] $utxos
     * @return Transaction
     * @throws \Exception
     */
    private function sign(TransactionInterface $transaction, array $utxos): Transaction
    {
        $privateKey = (new PrivateKeyFactory())->fromHexCompressed($this->hotWallet->private_key);
        $signer = new Signer($transaction);

        foreach ($utxos as $index => $utxo) {
            $txOut = new TransactionOutput(
                $utxo->value,
                ScriptFactory::scriptPubKey()->payToPubKeyHash($privateKey->getPubKeyHash())
            );

            $input = $signer->input($index, $txOut);
            $input->sign($privateKey);

            if (!$input->verify()) {
                throw new \Exception(trans('app.wrong-tx-signed-input', ['hash' => $utxo->hash]));
            }
        }

        $signed = $signer->get();

        return new Transaction($signed->getTxId()->getHex(), $signed->getHex(), $this->feeSatoshi);
    }
}
