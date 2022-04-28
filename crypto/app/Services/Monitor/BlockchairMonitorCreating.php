<?php

namespace App\Services\Crypto\Monitor;


trait BlockchairMonitorCreating
{
    public function createMonitor(): AbstractMonitor
    {
        return app(BlockchairMonitor::class, [
            'crypto' => $this->crypto,
            'confirmations' => $this->confirmations,
            'testnet' => $this->testnet,
        ]);
    }
}
