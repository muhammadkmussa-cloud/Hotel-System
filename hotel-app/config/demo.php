<?php

declare(strict_types=1);

return [
    'enabled' => env('DEMO_RESET_ENABLED', false),
    'token' => env('DEMO_RESET_TOKEN', ''),
    'connection' => [
        'driver' => 'mysql',
        'host' => env('DEMO_DB_HOST', ''),
        'port' => env('DEMO_DB_PORT', '3306'),
        'database' => env('DEMO_DB_DATABASE', ''),
        'username' => env('DEMO_DB_USERNAME', ''),
        'password' => env('DEMO_DB_PASSWORD', ''),
        'charset' => 'utf8mb4',
        'collation' => 'utf8mb4_unicode_ci',
        'prefix' => '',
        'strict' => true,
        'engine' => 'InnoDB',
        'timezone' => '+00:00',
        'options' => array_filter([
            PDO::ATTR_TIMEOUT => 5,
            PDO::ATTR_EMULATE_PREPARES => false,
            PDO::MYSQL_ATTR_MULTI_STATEMENTS => false,
            PDO::MYSQL_ATTR_SSL_CA => env('DEMO_DB_SSL_CA'),
            PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT => true,
        ], static fn ($value): bool => $value !== null && $value !== ''),
    ],
];
