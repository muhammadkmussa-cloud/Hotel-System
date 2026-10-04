<?php

declare(strict_types=1);

return [
    'default' => 'mysql',
    'migrations' => [
        'table' => 'migrations',
        'update_date_on_publish' => true,
    ],
    'connections' => [
        'demo_reset' => (require __DIR__ . '/demo.php')['connection'],
        'mysql' => [
            'driver' => 'mysql',
            'host' => env('DB_HOST', '127.0.0.1'),
            'port' => env('DB_PORT', '3306'),
            'database' => env('DB_DATABASE', ''),
            'username' => env('DB_USERNAME', ''),
            'password' => env('DB_PASSWORD', ''),
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
                PDO::MYSQL_ATTR_SSL_CA => env('DB_SSL_CA'),
                PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT => true,
            ], static fn ($value): bool => $value !== null && $value !== ''),
        ],
    ],
];
