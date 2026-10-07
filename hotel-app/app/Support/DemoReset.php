<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Contracts\Config\Repository;
use Illuminate\Database\DatabaseManager;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

final class DemoReset
{
    public function __construct(private readonly Repository $config, private readonly DatabaseManager $database) {}

    public function reset(mixed $confirmation): bool
    {
        $settings = $this->config->get('demo.connection', []);
        $token = $this->config->get('demo.token');
        $primary = $this->config->get('database.connections.mysql.database');
        if (! in_array($this->config->get('app.env'), ['local', 'testing'], true)
            || $this->config->get('demo.enabled') !== true
            || ! is_string($primary) || $primary === ''
            || ! is_string($token) || preg_match('/\A[a-f0-9]{64}\z/', $token) !== 1
        ) return false;
        foreach (['host', 'database', 'username', 'password'] as $field) {
            if (! is_string($settings[$field] ?? null) || $settings[$field] === '') return false;
        }
        if ($confirmation !== $settings['database']
            || strcasecmp($settings['database'], $primary) === 0
            || ! preg_match('/\A[A-Za-z0-9_.:\-]+\z/', $settings['host'])
            || ! preg_match('/\A[A-Za-z0-9_$\-]+\z/', $settings['database'])
            || filter_var($settings['port'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 65535]]) === false
        ) return false;

        $this->config->set('database.connections.demo_reset', $settings);
        $this->database->purge('demo_reset');
        try {
            $connection = $this->database->connection('demo_reset');
            // Refuse unexpected tables: future schema expansion needs an explicit reset review.
            $tables = $connection->getSchemaBuilder()->getTableListing($settings['database'], false);
            sort($tables);
            if ($tables !== ['demo_reset_guard', 'hotel_settings', 'idempotent_commands', 'migrations']) return false;
            if ($connection->getDriverName() !== 'mysql') return false;
            $engines = $connection->select('SELECT ENGINE AS engine FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE()');
            foreach ($engines as $table) {
                if ($table->engine !== 'InnoDB') return false;
            }
            DatabaseTransaction::run($connection, function () use ($connection, $token): void {
                $guard = $connection->table('demo_reset_guard')->lockForUpdate()->sole();
                if (($guard->purpose ?? null) !== 'isolated-demo' || ! hash_equals($token, (string) ($guard->token ?? ''))) {
                    throw new RuntimeException('Demo guard mismatch.');
                }
                $connection->table('idempotent_commands')->delete();
                $connection->table('hotel_settings')->delete();
                $connection->table('hotel_settings')->insert([
                    'id' => (string) Str::uuid7(), 'name' => 'DEMO — Not a live hotel',
                    'timezone' => 'Africa/Nairobi', 'currency' => 'KES',
                    'created_at' => now('UTC'), 'updated_at' => now('UTC'),
                ]);
            });

            return true;
        } catch (Throwable) {
            return false;
        } finally {
            $this->database->purge('demo_reset');
        }
    }
}
