<?php
declare(strict_types=1);

namespace App\Services\Crypto\Address;


use App\Services\Crypto\{BitWaspNetworkFactory, CryptoAddress};
use App\Services\Crypto\Utils\BitcoinCash\{AddressConverter, BitcoinCashNetworkInterface};
use BitWasp\Bitcoin\Address\PayToPubKeyHashAddress;
use BitWasp\Bitcoin\Bitcoin;
use BitWasp\Bitcoin\Crypto\Random\Random;
use BitWasp\Bitcoin\Key\Factory\PrivateKeyFactory;

trait BitWaspAddress
{
    public function generate(): CryptoAddress
    {
        $network = BitWaspNetworkFactory::getNetwork($this->crypto, $this->testnet);
        Bitcoin::setNetwork($network);

        $privateKeyFactory = new PrivateKeyFactory();
        $privateKey = $privateKeyFactory->generateCompressed(new Random());
        $publicKey = $privateKey->getPublicKey();
        $pubKeyHash160 = $publicKey->getPubKeyHash();
        $pubKeyHashAddress = new PayToPubKeyHashAddress($pubKeyHash160);

        $address = $pubKeyHashAddress->getAddress();
        if (is_a($network, BitcoinCashNetworkInterface::class)) {
            $address = AddressConverter::old2new($address);
        }

        $address = new CryptoAddress($privateKey->getHex(), $publicKey->getHex(), $address);
        $address->setWif($privateKey->toWif());
        $address->setTest($this->testnet);

        return $address;
    }
}
