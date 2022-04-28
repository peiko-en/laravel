<?php

return [
    'concrete' => [
        'api' => [
            'live' => [
                'Blockchair' => ['btc', 'doge'],
                'Trx' => ['trx'],
            ],
            'test' => [
                'Cryptoapis' => ['bch'],
                'Trx' => ['trx'],
            ]
        ],
        'transaction' => [
            'BitWasp' => ['btc', 'doge'],
            'Tron' => ['trx'],
        ],
        'broadcast' => [
            'live' => [
                'Blockchair' => ['btc', 'doge'],
                'Tron' => ['trx'],
            ],
            'test' => [
                'Smartbit' => ['btc'],
            ]
        ]
    ]
];
