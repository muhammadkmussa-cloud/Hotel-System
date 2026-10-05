<?php

declare(strict_types=1);

return [
    'mpesa' => [
        'merchant' => env('MPESA_MERCHANT_ID'),
        'shortcode' => env('MPESA_SHORTCODE'),
        'passkey' => env('MPESA_PASSKEY'),
    ],
    'fiscal' => [
        'enabled' => env('FISCAL_ENABLED', false),
        'endpoint' => env('FISCAL_ENDPOINT'),
    ],
];
