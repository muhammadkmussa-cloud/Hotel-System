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
        // The application always uses the connection named "mysql". Production
        // installations run MySQL (C21). DB_DRIVER=sqlite is a local/sandbox
        // and automated-test convenience only; it must not be used on a host.
        'mysql' => env('DB_DRIVER', 'mysql') === 'sqlite' ? [
            'driver' => 'sqlite',
            'database' => env('DB_DATABASE') ?: database_path('hotel.sqlite'),
            'prefix' => '',
            'foreign_key_constraints' => true,
            'busy_timeout' => 5000,
            'journal_mode' => 'WAL',
        ] : [
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
