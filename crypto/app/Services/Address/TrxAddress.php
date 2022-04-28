<?php
declare(strict_types=1);

namespace App\Services\Crypto\Address;

use App\Helpers\DIHelper;
use App\Services\Crypto\CryptoAddress;

class TrxAddress extends AbstractAddress
{
    public function generate(): CryptoAddress
    {
        $keyPairs = DIHelper::tronWallet()->generateAddress();

        return new CryptoAddress(
            $keyPairs->privateKey,
            $keyPairs->hexAddress,
            $keyPairs->address
        );
    }
}
