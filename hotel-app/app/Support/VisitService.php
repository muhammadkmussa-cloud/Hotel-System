<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Database\DatabaseManager;
use Illuminate\Database\QueryException;
use Illuminate\Support\Str;
use Throwable;

final class VisitService
{
    public function __construct(private readonly DatabaseManager $database) {}

    /**
     * Open a visit for a table. If an open visit already exists, return it
     * (idempotent) instead of creating a duplicate.
     *
     * @return array{id:string,created:bool}
     */
    public function open(string $tableId, string $actorId): array
    {
        $existing = $this->database->connection('mysql')->table('visits')
            ->where('table_id', $tableId)->where('state', 'open')
            ->first(['id']);
        if ($existing !== null) {
            return ['id' => (string) $existing->id, 'created' => false];
        }

        $id = (string) Str::uuid7();
        $now = now('UTC');
        try {
            $this->database->connection('mysql')->table('visits')->insert([
                'id' => $id, 'table_id' => $tableId, 'state' => 'open',
                'opened_at' => $now, 'created_at' => $now, 'updated_at' => $now,
            ]);
        } catch (QueryException $error) {
            // Lost a race against the unique index — return the winner.
            $winner = $this->database->connection('mysql')->table('visits')
                ->where('table_id', $tableId)->where('state', 'open')
                ->first(['id']);
            if ($winner !== null) {
                return ['id' => (string) $winner->id, 'created' => false];
            }
            throw $error;
        }

        return ['id' => $id, 'created' => true];
    }

    /**
     * Open visits with their table label, oldest first. Guests are attached by
     * the caller so this service stays free of guest concerns.
     *
     * @return list<array{id:string,tableId:string,tableLabel:string,openedAt:?string,version:int}>
     */
    public function active(): array
    {
        return $this->database->connection('mysql')->table('visits')
            ->join('tables', 'tables.id', '=', 'visits.table_id')
            ->where('visits.state', 'open')
            ->orderBy('visits.opened_at')
            ->get([
                'visits.id',
                'visits.table_id',
                'visits.opened_at',
                'visits.version',
                'tables.label as table_label',
            ])->map(static fn (object $row): array => [
                'id' => (string) $row->id,
                'tableId' => (string) $row->table_id,
                'tableLabel' => (string) $row->table_label,
                'openedAt' => $row->opened_at === null ? null : (string) $row->opened_at,
                'version' => (int) $row->version,
            ])->all();
    }

    /**
     * Close a visit using a versioned update.
     *
     * @return array{success:bool,version:?int}
     */
    public function close(string $visitId, string $actorId, ?int $expectedVersion = null): array
    {
        $connection = $this->database->connection('mysql');
        $visit = $connection->table('visits')->where('id', $visitId)->first(['state', 'version']);
        if ($visit === null) {
            return ['success' => false, 'version' => null];
        }
        if ($visit->state === 'closed') {
            return ['success' => true, 'version' => (int) $visit->version];
        }

        $now = now('UTC');
        $query = $connection->table('visits')->where('id', $visitId)->where('state', 'open');
        if ($expectedVersion !== null) {
            $query->where('version', $expectedVersion);
        }
        $affected = $query->update([
            'state' => 'closed', 'closed_at' => $now,
            'version' => $visit->version + 1, 'updated_at' => $now,
        ]);

        if ($affected !== 1) {
            return ['success' => false, 'version' => (int) $visit->version];
        }

        return ['success' => true, 'version' => (int) $visit->version + 1];
    }
}
