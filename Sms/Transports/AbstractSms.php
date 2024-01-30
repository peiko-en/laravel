<?php

declare(strict_types=1);

namespace App\Services\Sms\Transports;

use App\Dto\SmsDto;

abstract class AbstractSms
{
    public abstract function send(string $phone, string $message): SmsDto;
}
