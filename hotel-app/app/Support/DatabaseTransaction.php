<?php

declare(strict_types=1);

namespace App\Support;

use Closure;
use Illuminate\Database\Connection;
use LogicException;
use PDOException;

final class DatabaseTransaction
{
    public const MAX_ATTEMPTS = 3;

    /**
     * Replay the entire database-only callback on confirmed MySQL deadlock.
     * Reload records inside it; use durable outbox records for external effects.
     * The callback must not control transactions or execute DDL.
     */
    public static function run(Connection $connection, Closure $work): mixed
    {
        if ($connection->transactionLevel() !== 0 || $connection->getPdo()->inTransaction()) {
            throw new LogicException('Transaction retry requires a top-level transaction.');
        }

        for ($attempt = 1; ; $attempt++) {
            try {
                return $connection->transaction($work, 1);
            } catch (PDOException $error) {
                // Do not replay timeouts, lost connections, or ambiguous commits.
                if (($error->errorInfo[0] ?? null) !== '40001'
                    || ($error->errorInfo[1] ?? null) !== 1213
                    || $attempt >= self::MAX_ATTEMPTS
                    || $connection->transactionLevel() !== 0
                    || $connection->getPdo()->inTransaction()
                ) {
                    throw $error;
                }
                usleep($attempt * 10000);
            }
        }
    }
}
