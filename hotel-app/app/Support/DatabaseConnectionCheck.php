<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Contracts\Config\Repository;
use Illuminate\Database\DatabaseManager;
use Throwable;

final class DatabaseConnectionCheck
{
    public function __construct(private readonly Repository $config, private readonly DatabaseManager $database) {}

    public function passes(): bool
    {
        $settings = $this->config->get('database.connections.mysql', []);
        foreach (['host', 'database', 'username', 'password'] as $name) {
            if (! is_string($settings[$name] ?? null) || $settings[$name] === '') {
                return false;
            }
        }
        // Connection settings are private, but must not inject DSN/USE syntax.
        if (! preg_match('/\A[A-Za-z0-9_.:\-]+\z/', $settings['host'])
            || ! preg_match('/\A[A-Za-z0-9_$\-]+\z/', $settings['database'])
            || filter_var($settings['port'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 65535]]) === false) {
            return false;
        }

        try {
            $connection = $this->database->connection('mysql');
            $result = $connection->selectOne('SELECT VERSION() AS version, @@version_comment AS product');

            return str_contains(strtolower($result->product), 'mysql')
                && ! str_contains(strtolower($result->version), 'mariadb');
        } catch (Throwable) {
            // Do not report/log a driver exception: it can contain connection details.
            return false;
        } finally {
            $this->database->purge('mysql');
        }
    }
}
