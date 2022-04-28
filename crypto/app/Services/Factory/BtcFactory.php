<?php
declare(strict_types=1);

namespace App\Services\Crypto\Factory;

use App\Services\Crypto\Monitor\BlockchairMonitorCreating;

class BtcFactory extends AbstractCryptoFactory
{
    use BlockchairMonitorCreating;
}
