<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Database\Connection;
use InvalidArgumentException;
use Symfony\Component\HttpKernel\Exception\PreconditionFailedHttpException;

/** SQL primitive: callers authorize the row and supply already validated, database-ready fields. */
final class VersionedUpdate
{
    public static function apply(Connection $connection, string $table, string $id, int $expected, array $changes): int
    {
        if (! preg_match('/\A[a-z][a-z0-9_]{0,63}\z/', $table) || ! preg_match('/\A[a-f0-9]{8}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{12}\z/i', $id)
            || $expected < 1 || $expected >= PHP_INT_MAX || $changes === []) {
            throw new InvalidArgumentException('Invalid versioned update.');
        }
        foreach ($changes as $column => $value) {
            if (! is_string($column) || ! preg_match('/\A[a-z][a-z0-9_]{0,63}\z/', $column)
                || in_array($column, ['id', 'resource_version', 'created_at', 'updated_at'], true)
                || (! is_null($value) && ! is_scalar($value))) throw new InvalidArgumentException('Invalid versioned update fields.');
        }
        // The table must use a unique primary id. Compare and increment in one SQL statement.
        $affected = $connection->table($table)->where('id', $id)->where('resource_version', $expected)->update([
            ...$changes, 'resource_version' => $expected + 1, 'updated_at' => gmdate('Y-m-d H:i:s'),
        ]);
        if ($affected !== 1) throw new PreconditionFailedHttpException;
        return $expected + 1;
    }
}
