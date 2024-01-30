<?php

declare(strict_types=1);

namespace App\Services\Sms;

use App\Services\Sms\Transports\AbstractSms;
use Illuminate\Support\Str;

class TransportFactory
{
    public static function getInstance(string $alias): AbstractSms
    {
        try {
            $className = __NAMESPACE__ . '\Transports\\' . Str::studly($alias) . 'Sms';
            return new $className();
        } catch (\Throwable $e) {
            logger($e);
            throw $e;
        }
    }
}
