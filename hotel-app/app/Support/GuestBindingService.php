<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Database\DatabaseManager;
use Illuminate\Database\QueryException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use LogicException;
use Throwable;

/**
 * Staff-authorized binding between an enrolled device session and a guest.
 *
 * The binding is the only way a device acquires a guest identity: guest-facing
 * code resolves the guest from the authenticated device session alone and never
 * from a table/guest id supplied by the browser. Binding and revocation are
 * staff actions; a device cannot bind itself, and a guest cannot widen its own
 * scope by editing request fields.
 */
final class GuestBindingService
{
    /** Only tablets serve table guests; kiosk/kitchen/cashier modes cannot. */
    private const GUEST_DEVICE_MODE = 'tablet';

    public const MAX_TTL_MINUTES = 1440;

    public function __construct(private readonly DatabaseManager $database) {}

    /**
     * Bind a device session to a guest. Revokes any live binding that device
     * session already had, so one tablet is never two guests at once.
     *
     * @return array{result:string,id?:string} result is created|conflict|invalid_input|guest_not_found|visit_closed|device_session_not_found|device_session_inactive|wrong_mode|failed
     */
    public function bind(string $guestId, string $deviceSessionId, string $actorId, ?int $ttlMinutes = null): array
    {
        if (! Str::isUuid($guestId) || ! Str::isUuid($deviceSessionId) || $actorId === '') {
            return ['result' => 'invalid_input'];
        }
        if ($ttlMinutes !== null && ($ttlMinutes < 1 || $ttlMinutes > self::MAX_TTL_MINUTES)) {
            return ['result' => 'invalid_input'];
        }

        $connection = $this->database->connection('mysql');

        try {
            return DatabaseTransaction::run($connection, function () use ($connection, $guestId, $deviceSessionId, $actorId, $ttlMinutes): array {
                // Lock order matches order submission (visit → guest) to avoid a
                // deadlock when a guest orders while staff rebind the tablet.
                $peek = $connection->table('guests')->where('id', $guestId)->first(['id', 'visit_id']);
                if ($peek === null) {
                    return ['result' => 'guest_not_found'];
                }

                // A closed visit refuses new bindings regardless of the guest's
                // own state, so the caller sees the visit-level reason.
                $visit = $connection->table('visits')->where('id', $peek->visit_id)->lockForUpdate()->first(['state']);
                if ($visit === null || $visit->state !== 'open') {
                    return ['result' => 'visit_closed'];
                }

                $guest = $connection->table('guests')->where('id', $guestId)->lockForUpdate()->first(['id', 'visit_id', 'state']);
                if ($guest === null || $guest->state !== 'active') {
                    return ['result' => 'guest_not_found'];
                }

                $session = $connection->table('device_sessions')->where('id', $deviceSessionId)->lockForUpdate()
                    ->first(['id', 'device_id', 'expires_at', 'revoked_at']);
                if ($session === null) {
                    return ['result' => 'device_session_not_found'];
                }
                // Timestamps arrive as strings from the query builder; parse
                // explicitly instead of comparing a string with a Carbon.
                $expired = $session->expires_at === null
                    || Carbon::parse((string) $session->expires_at, 'UTC')->lessThanOrEqualTo(now('UTC'));
                if ($session->revoked_at !== null || $expired) {
                    return ['result' => 'device_session_inactive'];
                }

                $device = $connection->table('devices')->where('id', $session->device_id)->first(['active', 'mode']);
                if ($device === null || ! $device->active) {
                    return ['result' => 'device_session_inactive'];
                }
                if ($device->mode !== self::GUEST_DEVICE_MODE) {
                    return ['result' => 'wrong_mode'];
                }

                $now = now('UTC');
                // Replace any live binding this device session already holds.
                $connection->table('guest_bindings')
                    ->where('device_session_id', $deviceSessionId)
                    ->whereNull('revoked_at')
                    ->update(['revoked_at' => $now, 'updated_at' => $now]);

                $id = (string) Str::uuid7();
                $connection->table('guest_bindings')->insert([
                    'id' => $id,
                    'guest_id' => $guestId,
                    'device_session_id' => $deviceSessionId,
                    'bound_by_staff_user_id' => $actorId,
                    'bound_at' => $now,
                    'expires_at' => $ttlMinutes === null ? null : $now->copy()->addMinutes($ttlMinutes),
                    'revoked_at' => null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);

                return ['result' => 'created', 'id' => $id];
            });
        } catch (LogicException $error) {
            throw $error;
        } catch (QueryException $error) {
            return \App\Domain\Operations\JobRunner::isDuplicate($error) ? ['result' => 'conflict'] : ['result' => 'failed'];
        } catch (Throwable) {
            return ['result' => 'failed'];
        }
    }

    /** @return string revoked|invalid_input|not_found|already_revoked|failed */
    public function revoke(string $bindingId, string $actorId): string
    {
        if (! Str::isUuid($bindingId) || $actorId === '') {
            return 'invalid_input';
        }

        $connection = $this->database->connection('mysql');

        try {
            return DatabaseTransaction::run($connection, function () use ($connection, $bindingId): string {
                $binding = $connection->table('guest_bindings')->where('id', $bindingId)->lockForUpdate()
                    ->first(['revoked_at']);
                if ($binding === null) {
                    return 'not_found';
                }
                if ($binding->revoked_at !== null) {
                    return 'already_revoked';
                }

                $connection->table('guest_bindings')->where('id', $bindingId)->update([
                    'revoked_at' => now('UTC'),
                    'updated_at' => now('UTC'),
                ]);

                return 'revoked';
            });
        } catch (LogicException $error) {
            throw $error;
        } catch (Throwable) {
            return 'failed';
        }
    }

    /**
     * Replace a tablet: end the binding *and* the device session it used, so a
     * handed-over device cannot keep resolving the guest and any local draft
     * on it is stale by design. Guest identity and history are untouched.
     *
     * @return string revoked|invalid_input|not_found|already_revoked|failed
     */
    public function revokeWithDeviceSession(string $bindingId, string $actorId): string
    {
        if (! Str::isUuid($bindingId) || $actorId === '') {
            return 'invalid_input';
        }

        $connection = $this->database->connection('mysql');

        try {
            return DatabaseTransaction::run($connection, function () use ($connection, $bindingId): string {
                $binding = $connection->table('guest_bindings')->where('id', $bindingId)->lockForUpdate()
                    ->first(['revoked_at', 'device_session_id']);
                if ($binding === null) {
                    return 'not_found';
                }
                if ($binding->revoked_at !== null) {
                    return 'already_revoked';
                }

                $now = now('UTC');
                $connection->table('guest_bindings')->where('id', $bindingId)
                    ->update(['revoked_at' => $now, 'updated_at' => $now]);
                $connection->table('device_sessions')->where('id', $binding->device_session_id)
                    ->whereNull('revoked_at')
                    ->update(['revoked_at' => $now, 'updated_at' => $now]);

                return 'revoked';
            });
        } catch (LogicException $error) {
            throw $error;
        } catch (Throwable) {
            return 'failed';
        }
    }

    /**
     * Resolve the guest for an authenticated device session.
     *
     * No guest, table or visit identifier is accepted: identity comes from the
     * live binding only, so a forged request body cannot change whose bill or
     * orders a device can reach.
     *
     * @return array{bindingId:string,guestId:string,guestLabel:string,visitId:string,tableId:string,tableLabel:string}|null
     */
    public function resolveForDeviceSession(string $deviceSessionId): ?array
    {
        if (! Str::isUuid($deviceSessionId)) {
            return null;
        }

        $now = now('UTC');
        $row = $this->database->connection('mysql')->table('guest_bindings')
            ->join('guests', 'guests.id', '=', 'guest_bindings.guest_id')
            ->join('visits', 'visits.id', '=', 'guests.visit_id')
            ->join('tables', 'tables.id', '=', 'visits.table_id')
            ->join('device_sessions', 'device_sessions.id', '=', 'guest_bindings.device_session_id')
            ->join('devices', 'devices.id', '=', 'device_sessions.device_id')
            ->where('guest_bindings.device_session_id', $deviceSessionId)
            ->whereNull('guest_bindings.revoked_at')
            ->whereNull('device_sessions.revoked_at')
            ->where('device_sessions.expires_at', '>', $now)
            ->where('devices.active', 1)
            ->where('visits.state', 'open')
            ->where('guests.state', 'active')
            ->where(static function ($query) use ($now): void {
                $query->whereNull('guest_bindings.expires_at')->orWhere('guest_bindings.expires_at', '>', $now);
            })
            ->first([
                'guest_bindings.id as binding_id',
                'guest_bindings.expires_at',
                'guests.id as guest_id',
                'guests.label as guest_label',
                'visits.id as visit_id',
                'visits.table_id',
                'tables.label as table_label',
            ]);

        if ($row === null) {
            return null;
        }

        if ($row->expires_at !== null && Carbon::parse((string) $row->expires_at, 'UTC')->lessThanOrEqualTo($now)) {
            return null;
        }

        return [
            'bindingId' => (string) $row->binding_id,
            'guestId' => (string) $row->guest_id,
            'guestLabel' => (string) $row->guest_label,
            'visitId' => (string) $row->visit_id,
            'tableId' => (string) $row->table_id,
            'tableLabel' => (string) $row->table_label,
        ];
    }

    /** @return list<array{id:string,deviceName:string,boundAt:string,expiresAt:?string}> */
    public function activeForGuest(string $guestId): array
    {
        if (! Str::isUuid($guestId)) {
            return [];
        }

        $now = now('UTC');

        return $this->database->connection('mysql')->table('guest_bindings')
            ->join('device_sessions', 'device_sessions.id', '=', 'guest_bindings.device_session_id')
            ->join('devices', 'devices.id', '=', 'device_sessions.device_id')
            ->where('guest_bindings.guest_id', $guestId)
            ->whereNull('guest_bindings.revoked_at')
            ->whereNull('device_sessions.revoked_at')
            ->where('device_sessions.expires_at', '>', $now)
            ->where(static function ($query) use ($now): void {
                $query->whereNull('guest_bindings.expires_at')->orWhere('guest_bindings.expires_at', '>', $now);
            })
            ->orderBy('guest_bindings.bound_at')
            ->get([
                'guest_bindings.id',
                'devices.name as device_name',
                'guest_bindings.bound_at',
                'guest_bindings.expires_at',
            ])->map(static fn (object $row): array => [
                'id' => (string) $row->id,
                'deviceName' => (string) $row->device_name,
                'boundAt' => (string) $row->bound_at,
                'expiresAt' => $row->expires_at === null ? null : (string) $row->expires_at,
            ])->all();
    }

    /** @return array<string,list<array{id:string,deviceName:string,boundAt:string,expiresAt:?string}>> */
    public function forGuests(array $guestIds): array
    {
        $ids = array_values(array_filter($guestIds, static fn ($id): bool => is_string($id) && Str::isUuid($id)));
        $grouped = [];
        foreach ($ids as $id) {
            $grouped[$id] = [];
        }
        if ($ids === []) {
            return $grouped;
        }

        $now = now('UTC');
        foreach ($this->database->connection('mysql')->table('guest_bindings')
            ->join('device_sessions', 'device_sessions.id', '=', 'guest_bindings.device_session_id')
            ->join('devices', 'devices.id', '=', 'device_sessions.device_id')
            ->whereIn('guest_bindings.guest_id', $ids)
            ->whereNull('guest_bindings.revoked_at')
            ->whereNull('device_sessions.revoked_at')
            ->where('device_sessions.expires_at', '>', $now)
            ->where(static function ($query) use ($now): void {
                $query->whereNull('guest_bindings.expires_at')->orWhere('guest_bindings.expires_at', '>', $now);
            })
            ->orderBy('guest_bindings.bound_at')
            ->get([
                'guest_bindings.id',
                'guest_bindings.guest_id',
                'devices.name as device_name',
                'guest_bindings.bound_at',
                'guest_bindings.expires_at',
            ]) as $row) {
            $guestId = (string) $row->guest_id;
            $grouped[$guestId][] = [
                'id' => (string) $row->id,
                'deviceName' => (string) $row->device_name,
                'boundAt' => (string) $row->bound_at,
                'expiresAt' => $row->expires_at === null ? null : (string) $row->expires_at,
            ];
        }

        return $grouped;
    }
}
