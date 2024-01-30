<?php

declare(strict_types=1);

namespace App\Services\Sms\Transports;

use App\Dto\SmsDto;
use App\Helpers\TelegramHelper;

class TelegramSms extends AbstractSms
{
    public function send(string $phone, string $message): SmsDto
    {
        TelegramHelper::sendSms($phone, $message);

        return new SmsDto(['phone' => $phone]);
    }
}
