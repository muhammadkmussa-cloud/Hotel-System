<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Database\DatabaseManager;
use Illuminate\Database\Connection;
use Illuminate\Database\QueryException;
use Illuminate\Support\Str;
use Throwable;

/**
 * Guest membership inside one visit.
 *
 * A guest is an independent, long-lived identity: it is never derived from a
 * tablet, device session or request, so replacing a device cannot change who
 * the guest is or renumber the table. Numbering is assigned by the server
 * under the visit row lock, and both the number and the label are unique
 * inside their visit only (two visits may both have "Guest 1").
 */
final class GuestService
{
    public const MAX_NAME_LENGTH = 150;

    /** Practical per-table bound; a waiter adds guests through staff screens. */
    public const MAX_GUESTS_PER_VISIT = 32;

    public function __construct(private readonly DatabaseManager $database) {}

    /**
     * Add the next guest to an open visit.
     *
     * @return array{result:string,id?:string,label?:string,displayNumber?:int}
     */
    public function add(string $visitId, string $actorId, ?string $name = null): array
    {
        if (! Str::isUuid($visitId) || $actorId === '') {
            return ['result' => 'invalid_input'];
        }

        $name = $name === null ? null : trim($name);
        if ($name !== null
            && ($name === ''
                || mb_strlen($name) > self::MAX_NAME_LENGTH
                || str_contains($name, "\0")
                || preg_match('/[\x00-\x1F\x7F]/', $name) === 1)
        ) {
            return ['result' => 'invalid_input'];
        }

        $connection = $this->database->connection('mysql');

        try {
            return DatabaseTransaction::run($connection, function () use ($connection, $visitId, $name): array {
                // The visit lock serialises concurrent "add guest" requests so
                // two waiters cannot both claim the same number.
                $visit = $connection->table('visits')->where('id', $visitId)->lockForUpdate()->first(['state']);
                if ($visit === null) {
                    return ['result' => 'not_found'];
                }
                if ($visit->state !== 'open') {
                    return ['result' => 'visit_closed'];
                }

                $used = $connection->table('guests')->where('visit_id', $visitId)
                    ->pluck('display_number')->map(static fn ($value): int => (int) $value)->all();
                $displayNumber = $used === [] ? 1 : max($used) + 1;
                if ($displayNumber > self::MAX_GUESTS_PER_VISIT) {
                    return ['result' => 'limit_reached'];
                }

                $id = (string) Str::uuid7();
                $now = now('UTC');
                $connection->table('guests')->insert([
                    'id' => $id,
                    'visit_id' => $visitId,
                    'display_number' => $displayNumber,
                    'label' => 'Guest '.$displayNumber,
                    'name' => $name,
                    'state' => 'active',
                    'version' => 1,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);

                return ['result' => 'created', 'id' => $id, 'label' => 'Guest '.$displayNumber, 'displayNumber' => $displayNumber];
            });
        } catch (QueryException $error) {
            // The unique (visit_id, display_number)/(visit_id, label) indexes
            // are the final guard if the lock is ever bypassed.
            return \App\Domain\Operations\JobRunner::isDuplicate($error) ? ['result' => 'duplicate_label'] : ['result' => 'failed'];
        } catch (Throwable) {
            return ['result' => 'failed'];
        }
    }

    /** @return list<array{id:string,displayNumber:int,label:string,name:?string,state:string,version:int}> */
    public function list(string $visitId): array
    {
        if (! Str::isUuid($visitId)) {
            return [];
        }

        return $this->database->connection('mysql')->table('guests')
            ->where('visit_id', $visitId)
            ->orderBy('display_number')
            ->get(['id', 'display_number', 'label', 'name', 'state', 'version'])
            ->map(static fn (object $row): array => [
                'id' => (string) $row->id,
                'displayNumber' => (int) $row->display_number,
                'label' => (string) $row->label,
                'name' => $row->name === null ? null : (string) $row->name,
                'state' => (string) $row->state,
                'version' => (int) $row->version,
            ])->all();
    }

    /** @return array<string,list<array{id:string,displayNumber:int,label:string,name:?string,state:string,version:int}>> */
    public function forVisits(array $visitIds): array
    {
        $ids = array_values(array_filter($visitIds, static fn ($id): bool => is_string($id) && Str::isUuid($id)));
        if ($ids === []) {
            return [];
        }

        $grouped = [];
        foreach ($ids as $id) {
            $grouped[$id] = [];
        }
        foreach ($this->database->connection('mysql')->table('guests')->whereIn('visit_id', $ids)
            ->orderBy('display_number')
            ->get(['id', 'visit_id', 'display_number', 'label', 'name', 'state', 'version']) as $row) {
            $visitId = (string) $row->visit_id;
            $grouped[$visitId][] = [
                'id' => (string) $row->id,
                'displayNumber' => (int) $row->display_number,
                'label' => (string) $row->label,
                'name' => $row->name === null ? null : (string) $row->name,
                'state' => (string) $row->state,
                'version' => (int) $row->version,
            ];
        }

        return $grouped;
    }

    /**
     * Read one guest directly. Used to prove identity survives device changes.
     *
     * @return array{id:string,visitId:string,displayNumber:int,label:string,name:?string,state:string}|null
     */
    public function find(string $guestId): ?array
    {
        if (! Str::isUuid($guestId)) {
            return null;
        }

        $row = $this->database->connection('mysql')->table('guests')->where('id', $guestId)
            ->first(['id', 'visit_id', 'display_number', 'label', 'name', 'state']);
        if ($row === null) {
            return null;
        }

        return [
            'id' => (string) $row->id,
            'visitId' => (string) $row->visit_id,
            'displayNumber' => (int) $row->display_number,
            'label' => (string) $row->label,
            'name' => $row->name === null ? null : (string) $row->name,
            'state' => (string) $row->state,
        ];
    }

    /** Exposed for fixture probes that must not bypass the service. */
    public function connection(): Connection
    {
        return $this->database->connection('mysql');
    }
}
