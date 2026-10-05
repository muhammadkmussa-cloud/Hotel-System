<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Database\DatabaseManager;
use Illuminate\Database\QueryException;
use Illuminate\Support\Str;
use Throwable;

/**
 * Visit lifecycle with a database-enforced one-open-visit-per-table guard.
 */
final class VisitService
{
    public function __construct(private readonly DatabaseManager $database) {}

    /**
     * @return array{visitId:?string, conflict:bool}
     */
    public function open(string $tableId, string $actorId): array
    {
        if ($tableId === '' || ! Str::isUuid($tableId) || $actorId === '') {
            return ['visitId' => null, 'conflict' => false];
        }
        $connection = $this->database->connection('mysql');
        try {
            $connection->transaction(function () use ($connection, $tableId): void {
                $connection->table('visits')->insert([
                    'id' => (string) Str::uuid7(),
                    'table_id' => $tableId,
                    'state' => 'open',
                    'opened_at' => now('UTC'),
                    'created_at' => now('UTC'),
                    'updated_at' => now('UTC'),
                ]);
            });
        } catch (QueryException $error) {
            if (($error->errorInfo[1] ?? null) === 1062) {
                return ['visitId' => null, 'conflict' => true];
            }

            return ['visitId' => null, 'conflict' => false];
        }

        $id = $connection->table('visits')->where('table_id', $tableId)->where('state', 'open')->value('id');

        return ['visitId' => $id !== null ? (string) $id : null, 'conflict' => false];
    }

    /** @return string closed|not_found|failed */
    public function close(string $visitId): string
    {
        if ($visitId === '' || ! Str::isUuid($visitId)) {
            return 'not_found';
        }
        $connection = $this->database->connection('mysql');
        $row = $connection->table('visits')->where('id', $visitId)->first(['state']);
        if ($row === null) {
            return 'not_found';
        }
        if ($row->state === 'closed') {
            return 'closed';
        }

        try {
            $connection->transaction(function () use ($connection, $visitId): void {
                $connection->table('visits')->where('id', $visitId)->update([
                    'state' => 'closed',
                    'closed_at' => now('UTC'),
                    'updated_at' => now('UTC'),
                ]);
            });
        } catch (Throwable) {
            return 'failed';
        }

        return 'closed';
    }
}
