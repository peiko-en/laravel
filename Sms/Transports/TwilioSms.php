<?php

declare(strict_types=1);

namespace App\Services\Sms\Transports;

use App\Dto\SmsDto;
use App\Helpers\Formatter;
use Illuminate\Support\Str;
use Twilio\Rest\Client;

class TwilioSms extends AbstractSms
{
    private Client $client;
    private string $from;

    public function __construct()
    {
        $this->from = (string) config('sms.twilio.from');

        $this->client = app(Client::class, [
            'username' => config('sms.twilio.sid'),
            'password' => config('sms.twilio.auth_token'),
        ]);
    }

    public function send(string $phone, string $message): SmsDto
    {
        $smsDto = new SmsDto(['phone' => $phone, 'sid' => $message->sid ?? null]);

        try {
            $message = $this->client->messages->create(Formatter::signedPhone($phone), [
                'from' => $this->from,
                'body' => $message,
                'statusCallback' => route('twilio.webhook')
            ]);

            $smsDto->sid = $message->sid ?? null;

            return $smsDto;
        } catch (\Throwable $e) {
            $error = $e->getMessage();

            if (Str::contains($error, 'is not a valid')) {
                $error = trans('app.phone-number-not-valid', ['phone' => $phone]);
            }

            $smsDto->error = $error;
        }

        return $smsDto;
    }
}
