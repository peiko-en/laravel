<?php

declare(strict_types=1);

namespace App\Services\Sms\Transports;

use App\Dto\SmsDto;
use App\Exceptions\CommonException;
use Plivo\RestClient;

class PlivoSms extends AbstractSms
{
    private RestClient $client;
    private string $from;

    public function __construct()
    {
        $this->from = (string) config('sms.plivo.from');

        $this->client = app(RestClient::class, [
            'authId' => config('sms.plivo.auth_id'),
            'authToken' => config('sms.plivo.auth_token')
        ]);
    }

    public function send(string $phone, string $message): SmsDto
    {
        $smsDto = new SmsDto(['phone' => $phone]);

        try {
            $response = $this->client->messages->create([
                'src' => $this->from,
                'dst' => $phone,
                'text' => $message
            ]);

            logger()->channel('sms')->debug(json_encode($response));

            return $smsDto;
        } catch (\Throwable $e) {
            logger($e);
            $smsDto->error = $e->getMessage();

            return $smsDto;
        }
    }
}
