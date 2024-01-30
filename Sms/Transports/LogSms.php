<?php

declare(strict_types=1);

namespace App\Services\Sms\Transports;

use App\Dto\SmsDto;

class LogSms extends AbstractSms
{
    public function send(string $phone, string $message): SmsDto
    {
        logger()->channel('sms')->debug($phone . ': ' . $message);

        return new SmsDto(['phone' => $phone, 'cid' => time()]);
    }
}
