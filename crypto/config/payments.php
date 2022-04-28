<?php

return [
    'perfect-money' => [
        'payeeAccountUsd' => env('PM_PAYEE_ACCOUNT_USD'),
        'payeeAccountEur' => env('PM_PAYEE_ACCOUNT_EUR'),
        'secret' => env('PM_SECRET')
    ],
    'btc' => [
        'confirmations' => 3,
        'testnet' => env('BTC_TESTNET', false),
    ],
    'doge' => [
        'confirmations' => 3,
        'testnet' => env('DOGE_TESTNET', false),
    ],
    'trx' => [
        'confirmations' => 3,
        'testnet' => env('TRON_TESTNET', false),
    ],
];
