<?php

declare(strict_types=1);

namespace App\Support;

use Closure;
use Illuminate\Database\MySqlConnection;
use Illuminate\Database\QueryException;
use InvalidArgumentException;
use RuntimeException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

final class IdempotentCommand
{
    /**
     * Authenticate/authorize before calling. Scope and operation are server-owned, including resource identity.
     * Work must use this connection only: no DDL, manual transactions, or external side effects.
     * @param Closure(MySqlConnection): CommandResult $work
     */
    public static function run(MySqlConnection $connection, string $scope, string $operation, string $key, string $body, Closure $work): CommandResult
    {
        if ($scope === '' || strlen($scope) > 255 || $operation === '' || strlen($operation) > 255
            || ! preg_match('/\A[A-Za-z0-9_-]{16,128}\z/', $key) || strlen($body) > 65536) {
            throw new InvalidArgumentException('Invalid idempotent command identity or body.');
        }
        $identity = hash('sha256', json_encode([$scope, $operation, $key], JSON_THROW_ON_ERROR));
        $bodyHash = hash('sha256', $body);
        return DatabaseTransaction::run($connection, function () use ($connection, $identity, $bodyHash, $work): CommandResult {
            $created = true;
            try {
                $connection->table('idempotent_commands')->insert([
                    'identity_hash' => $identity, 'body_hash' => $bodyHash, 'created_at' => gmdate('Y-m-d H:i:s'),
                ]);
            } catch (QueryException $error) {
                if (($error->errorInfo[0] ?? null) !== '23000' || ($error->errorInfo[1] ?? null) !== 1062) throw $error;
                $created = false;
            }
            $record = $connection->table('idempotent_commands')->where('identity_hash', $identity)->lockForUpdate()->first();
            if ($record === null) throw new RuntimeException('Idempotency record unavailable.');
            if (! hash_equals($record->body_hash, $bodyHash)) throw new ConflictHttpException;
            if (! $created) {
                if (! is_int($record->result_status) || ! is_string($record->result_data) || strlen($record->result_data) > 65536) {
                    throw new RuntimeException('Idempotency result unavailable.');
                }
                $data = json_decode($record->result_data, true, 32, JSON_THROW_ON_ERROR | JSON_BIGINT_AS_STRING);
                if (! is_array($data)) throw new RuntimeException('Idempotency result unavailable.');
                return new CommandResult($data, $record->result_status, true);
            }
            $result = $work($connection);
            if (! $result instanceof CommandResult) throw new RuntimeException('Invalid command result.');
            $encoded = json_encode($result->data, JSON_THROW_ON_ERROR | JSON_PRESERVE_ZERO_FRACTION, 32);
            if (strlen($encoded) > 65536) throw new RuntimeException('Command result too large.');
            $connection->table('idempotent_commands')->where('identity_hash', $identity)->update([
                'result_status' => $result->status, 'result_data' => $encoded,
            ]);
            return new CommandResult($result->data, $result->status);
        });
    }
}
