<?php
declare(strict_types=1);

namespace App\Services\Crypto\Factory;

use App\Services\Crypto\Monitor\TronMonitor;

class TrxFactory extends AbstractCryptoFactory
{
    public function createMonitor(): TronMonitor
    {
        return app(TronMonitor::class, [
            'crypto' => $this->crypto,
            'confirmations' => $this->confirmations,
            'testnet' => $this->testnet
        ]);
    }
}
