<?php


namespace App\Services\Payments;


class Bitcoin extends Coinpayments
{
    protected $currency = 'BTC';
    protected $configKey = 'coinpayments';
}