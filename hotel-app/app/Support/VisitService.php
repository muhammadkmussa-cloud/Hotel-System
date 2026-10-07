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
        // The opening waiter owns the visit until a manager transfers it.
        $ownerWaiterId = Str::isUuid($actorId) ? $actorId : null;
        try {
            $this->database->connection('mysql')->table('visits')->insert([
                'id' => $id, 'table_id' => $tableId, 'owner_waiter_id' => $ownerWaiterId, 'state' => 'open',
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
     * Move an open visit to another table and/or reassign its owning waiter.
     *
     * The visit row is locked for the whole check-and-write, the destination
     * table is locked before its occupancy test, and the expected version must
     * match, so two managers cannot both move the same visit or move two
     * visits into one table. Guests, bindings and orders keep their identity.
     *
     * @return array{result:string,version:?int} result is transferred|unchanged|invalid_input|not_found|visit_closed|version_conflict|destination_occupied|destination_not_found|waiter_not_found|failed
     */
    public function transfer(
        string $visitId,
        ?string $targetTableId,
        ?string $targetWaiterId,
        string $actorId,
        ?int $expectedVersion = null,
    ): array {
        if (! Str::isUuid($visitId) || $actorId === '') {
            return ['result' => 'invalid_input', 'version' => null];
        }
        if ($targetTableId !== null && ! Str::isUuid($targetTableId)) {
            return ['result' => 'invalid_input', 'version' => null];
        }
        if ($targetWaiterId !== null && ! Str::isUuid($targetWaiterId)) {
            return ['result' => 'invalid_input', 'version' => null];
        }
        if ($targetTableId === null && $targetWaiterId === null) {
            return ['result' => 'unchanged', 'version' => null];
        }

        $connection = $this->database->connection('mysql');

        try {
            return DatabaseTransaction::run($connection, function () use ($connection, $visitId, $targetTableId, $targetWaiterId, $expectedVersion): array {
                $visit = $connection->table('visits')->where('id', $visitId)->lockForUpdate()
                    ->first(['table_id', 'state', 'version', 'owner_waiter_id']);
                if ($visit === null) {
                    return ['result' => 'not_found', 'version' => null];
                }
                $version = (int) $visit->version;
                if ($visit->state !== 'open') {
                    return ['result' => 'visit_closed', 'version' => $version];
                }
                if ($expectedVersion !== null && $version !== $expectedVersion) {
                    return ['result' => 'version_conflict', 'version' => $version];
                }

                $changes = [];
                if ($targetTableId !== null && (string) $visit->table_id !== $targetTableId) {
                    $table = $connection->table('tables')->where('id', $targetTableId)->lockForUpdate()->first(['active']);
                    if ($table === null || ! $table->active) {
                        return ['result' => 'destination_not_found', 'version' => $version];
                    }
                    if ($connection->table('visits')->where('table_id', $targetTableId)->where('state', 'open')->exists()) {
                        return ['result' => 'destination_occupied', 'version' => $version];
                    }
                    $changes['table_id'] = $targetTableId;
                }

                if ($targetWaiterId !== null && (string) ($visit->owner_waiter_id ?? '') !== $targetWaiterId) {
                    $waiter = $connection->table('staff_users')->where('id', $targetWaiterId)->first(['active']);
                    if ($waiter === null || ! $waiter->active) {
                        return ['result' => 'waiter_not_found', 'version' => $version];
                    }
                    $changes['owner_waiter_id'] = $targetWaiterId;
                }

                if ($changes === []) {
                    return ['result' => 'unchanged', 'version' => $version];
                }

                $changes['version'] = $version + 1;
                $changes['updated_at'] = now('UTC');
                $connection->table('visits')->where('id', $visitId)->update($changes);

                return ['result' => 'transferred', 'version' => $version + 1];
            });
        } catch (QueryException $error) {
            // The active-table unique index is the final occupancy guard.
            return \App\Domain\Operations\JobRunner::isDuplicate($error)
                ? ['result' => 'destination_occupied', 'version' => null]
                : ['result' => 'failed', 'version' => null];
        } catch (Throwable) {
            return ['result' => 'failed', 'version' => null];
        }
    }

    /**
     * Waiter table overview (S03): every active table with its open visit, if
     * any, and the number of guests in it.
     *
     * Balances and kitchen status are deliberately absent: billing arrives in
     * P18/P19, and the screen labels them as placeholders rather than showing
     * numbers the backend cannot yet derive.
     *
     * @return list<array{tableId:string,label:string,visitId:?string,openedAt:?string,guestCount:int,version:?int}>
     */
    public function overview(): array
    {
        $connection = $this->database->connection('mysql');

        $openVisits = [];
        foreach ($connection->table('visits')->where('state', 'open')->get(['id', 'table_id', 'opened_at', 'version']) as $visit) {
            $openVisits[(string) $visit->table_id] = $visit;
        }

        $guestCounts = [];
        foreach ($connection->table('guests')
            ->join('visits', 'visits.id', '=', 'guests.visit_id')
            ->where('visits.state', 'open')
            ->groupBy('guests.visit_id')
            ->get(['guests.visit_id as visit_id', $connection->raw('COUNT(*) as total')]) as $row) {
            $guestCounts[(string) $row->visit_id] = (int) $row->total;
        }

        return $connection->table('tables')->where('active', 1)->orderBy('label')->get(['id', 'label'])
            ->map(static function (object $table) use ($openVisits, $guestCounts): array {
                $visit = $openVisits[(string) $table->id] ?? null;

                return [
                    'tableId' => (string) $table->id,
                    'label' => (string) $table->label,
                    'visitId' => $visit === null ? null : (string) $visit->id,
                    'openedAt' => $visit === null || $visit->opened_at === null ? null : (string) $visit->opened_at,
                    'guestCount' => $visit === null ? 0 : ($guestCounts[(string) $visit->id] ?? 0),
                    'version' => $visit === null ? null : (int) $visit->version,
                ];
            })->all();
    }

    /**
     * One visit with its table label and waiter. Returns null when absent.
     *
     * @return array{id:string,tableId:string,tableLabel:string,state:string,openedAt:?string,closedAt:?string,version:int,ownerWaiterId:?string,ownerWaiterName:?string}|null
     */
    public function visit(string $visitId): ?array
    {
        if (! Str::isUuid($visitId)) {
            return null;
        }

        $row = $this->database->connection('mysql')->table('visits')
            ->join('tables', 'tables.id', '=', 'visits.table_id')
            ->leftJoin('staff_users', 'staff_users.id', '=', 'visits.owner_waiter_id')
            ->where('visits.id', $visitId)
            ->first([
                'visits.id',
                'visits.table_id',
                'visits.state',
                'visits.opened_at',
                'visits.closed_at',
                'visits.version',
                'visits.owner_waiter_id',
                'tables.label as table_label',
                'staff_users.name as waiter_name',
            ]);

        if ($row === null) {
            return null;
        }

        return [
            'id' => (string) $row->id,
            'tableId' => (string) $row->table_id,
            'tableLabel' => (string) $row->table_label,
            'state' => (string) $row->state,
            'openedAt' => $row->opened_at === null ? null : (string) $row->opened_at,
            'closedAt' => $row->closed_at === null ? null : (string) $row->closed_at,
            'version' => (int) $row->version,
            'ownerWaiterId' => $row->owner_waiter_id === null ? null : (string) $row->owner_waiter_id,
            'ownerWaiterName' => $row->waiter_name === null ? null : (string) $row->waiter_name,
        ];
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
            return ['success' => false, 'version' => null, 'blockers' => []];
        }
        if ($visit->state === 'closed') {
            return ['success' => true, 'version' => (int) $visit->version, 'blockers' => []];
        }

        return DatabaseTransaction::run($connection, function () use ($connection, $visitId, $visit, $expectedVersion, $actorId): array {
            $connection->table('visits')->where('id', $visitId)->lockForUpdate()->first();
            $blockers = \App\Domain\Ordering\VisitGuards::blockers($visitId);
            if ($blockers !== []) {
                return ['success' => false, 'version' => (int) $visit->version, 'blockers' => $blockers];
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
                return ['success' => false, 'version' => (int) $visit->version, 'blockers' => []];
            }
            // Closing ends every guest identity on every tablet.
            $guestIds = $connection->table('guests')->where('visit_id', $visitId)->pluck('id')->all();
            $connection->table('guest_bindings')->whereIn('guest_id', $guestIds)->whereNull('revoked_at')->update(['revoked_at' => $now, 'updated_at' => $now]);
            $connection->table('guests')->whereIn('id', $guestIds)->update(['state' => 'settled', 'updated_at' => $now]);
            $connection->table('service_requests')->where('visit_id', $visitId)->where('state', '!=', 'resolved')
                ->update(['state' => 'resolved', 'resolved_by' => $actorId, 'resolution' => 'Visit closed', 'updated_at' => $now]);
            \App\Domain\Audit::record('visit_closed', $actorId, ['visit_id' => $visitId]);
            \App\Domain\Outbox::emit('visit.closed', 'visit:'.$visitId);
            \App\Domain\Outbox::emit('visit.closed', 'staff', ['visit_id' => $visitId]);

            return ['success' => true, 'version' => (int) $visit->version + 1, 'blockers' => []];
        });
    }
}
