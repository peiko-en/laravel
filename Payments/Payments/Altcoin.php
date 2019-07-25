<?php


namespace App\Services\Payments;


class Altcoin extends Coinpayments
{
    protected $currency = 'BTC';
    protected $configKey = 'coinpayments';
}