<?php

declare(strict_types=1);

return [
    'mpesa' => [
        // simulator (default, never moves money) | sandbox | production
        'mode' => env('MPESA_MODE', 'simulator'),
        'merchant' => env('MPESA_MERCHANT_ID'),
        'shortcode' => env('MPESA_SHORTCODE'),
        'party_b' => env('MPESA_PARTY_B'),
        'transaction_type' => env('MPESA_TRANSACTION_TYPE', 'CustomerPayBillOnline'),
        'passkey' => env('MPESA_PASSKEY'),
        'consumer_key' => env('MPESA_CONSUMER_KEY'),
        'consumer_secret' => env('MPESA_CONSUMER_SECRET'),
        // Public HTTPS callback including the secret path token, e.g.
        // https://hotel.example/callbacks/mpesa/<MPESA_CALLBACK_TOKEN>
        'callback_url' => env('MPESA_CALLBACK_URL'),
        'callback_token' => env('MPESA_CALLBACK_TOKEN'),
    ],
    'fiscal' => [
        'enabled' => env('FISCAL_ENABLED', false),
        // simulator (labelled, not a tax invoice) | etims (adapter not bundled)
        'provider' => env('FISCAL_PROVIDER', 'simulator'),
        'endpoint' => env('FISCAL_ENDPOINT'),
    ],
    'print_bridge' => [
        // Bridge credentials are hashed database identities provisioned with
        // hotel:create-print-bridge; no installation-wide bearer token exists.
        'receipt_destination' => env('PRINT_RECEIPT_DESTINATION'),
    ],
];
