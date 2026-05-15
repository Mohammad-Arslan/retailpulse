<?php

declare(strict_types=1);

return [
    'identifiers' => [
        'sku' => [
            'key' => 'default_sku',
            'prefix' => 'RP-',
            'suffix' => '',
            'pad_length' => 6,
            'format' => 'internal',
        ],
        'barcode' => [
            'key' => 'default_barcode',
            'prefix' => '',
            'suffix' => '',
            'pad_length' => 12,
            'format' => 'ean13',
            'ean_company_prefix' => '5900000',
        ],
    ],
];
