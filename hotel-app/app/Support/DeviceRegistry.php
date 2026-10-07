<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Database\DatabaseManager;
use Illuminate\Database\QueryException;
use Illuminate\Support\Str;
use Throwable;

/**
 * Enrolled devices and their sessions. Only SHA-256 digests are stored; raw
 * credentials and session tokens are never persisted.
 */
final class DeviceRegistry
{
    public function __construct(private readonly DatabaseManager $database) {}

    /** @return list<array{id:string,name:string,mode:string,active:bool}> */
    public function activeDevices(): array
    {
        return $this->database->connection('mysql')->table('devices')
            ->where('active', 1)->orderBy('name')->get(['id', 'name', 'mode', 'active'])->all();
    }

    /** @return list<array{id:string,name:string,mode:string,active:bool}> */
    public function list(): array
    {
        return $this->database->connection('mysql')->table('devices')
            ->orderBy('name')->get(['id', 'name', 'mode', 'active'])->all();
    }

    /** @return string deactivated|invalid_input|not_found|already_inactive|failed */
    public function deactivate(string $id): string
    {
        if ($id === '' || ! Str::isUuid($id)) {
            return 'invalid_input';
        }
        $connection = $this->database->connection('mysql');
        $row = $connection->table('devices')->where('id', $id)->first(['active']);
        if ($row === null) {
            return 'not_found';
        }
        if (! $row->active) {
            return 'already_inactive';
        }

        try {
            $connection->transaction(function () use ($connection, $id): void {
                $connection->table('devices')->where('id', $id)->update([
                    'active' => 0,
                    'updated_at' => now('UTC'),
                ]);
            });

            return 'deactivated';
        } catch (Throwable) {
            return 'failed';
        }
    }

    /**
     * Enroll a device and issue a short-lived pairing code linked to it.
     * @return array{result:string, deviceId?:string, code?:string} result is enrolled|invalid_input|failed
     */
    public function enroll(string $name, string $mode, string $credential): array
    {
        $name = trim($name);
        if ($name === '' || mb_strlen($name) > 64
            || ! in_array($mode, ['tablet', 'kiosk', 'cashier', 'kitchen', 'collection'], true)
            || strlen($credential) < 16
        ) {
            return ['result' => 'invalid_input'];
        }

        try {
            $deviceId = (string) Str::uuid7();
            $this->database->connection('mysql')->table('devices')->insert([
                'id' => $deviceId,
                'name' => $name,
                'mode' => $mode,
                'credential_digest' => hash('sha256', $credential),
                'active' => 1,
                'created_at' => now('UTC'),
                'updated_at' => now('UTC'),
            ]);
        } catch (Throwable) {
            return ['result' => 'failed'];
        }

        $code = (new DevicePairing($this->database))->issue($deviceId, 15);

        return ['result' => 'enrolled', 'deviceId' => $deviceId, 'code' => $code];
    }

    /**
     * Sessions a staff member may still bind: device active, session neither
     * revoked nor expired. Optionally filtered to one device mode.
     *
     * @return list<array{sessionId:string,deviceId:string,deviceName:string,mode:string,lastSeenAt:?string}>
     */
    public function activeSessions(?string $mode = null): array
    {
        $query = $this->database->connection('mysql')->table('device_sessions')
            ->join('devices', 'devices.id', '=', 'device_sessions.device_id')
            ->where('devices.active', 1)
            ->whereNull('device_sessions.revoked_at')
            ->where('device_sessions.expires_at', '>', now('UTC'));

        if ($mode !== null) {
            $query->where('devices.mode', $mode);
        }

        return $query->orderBy('devices.name')
            ->get([
                'device_sessions.id as session_id',
                'device_sessions.device_id',
                'devices.name as device_name',
                'devices.mode',
                'device_sessions.last_seen_at',
            ])->map(static fn (object $row): array => [
                'sessionId' => (string) $row->session_id,
                'deviceId' => (string) $row->device_id,
                'deviceName' => (string) $row->device_name,
                'mode' => (string) $row->mode,
                'lastSeenAt' => $row->last_seen_at === null ? null : (string) $row->last_seen_at,
            ])->all();
    }

    /** @return string created|invalid_input|not_found|failed */
    public function createSession(string $deviceId, string $token, int $ttlMinutes): string
    {
        if ($deviceId === '' || ! Str::isUuid($deviceId) || $token === '' || $ttlMinutes < 1) {
            return 'invalid_input';
        }
        $connection = $this->database->connection('mysql');
        if (! $connection->table('devices')->where('id', $deviceId)->exists()) {
            return 'not_found';
        }

        try {
            $connection->table('device_sessions')->insert([
                'id' => (string) Str::uuid7(),
                'device_id' => $deviceId,
                'token_digest' => hash('sha256', $token),
                'issued_at' => now('UTC'),
                'expires_at' => now('UTC')->addMinutes($ttlMinutes),
                'created_at' => now('UTC'),
                'updated_at' => now('UTC'),
            ]);

            return 'created';
        } catch (QueryException $error) {
            return \App\Domain\Operations\JobRunner::isDuplicate($error) ? 'invalid_input' : 'failed';
        } catch (Throwable) {
            return 'failed';
        }
    }

    /** @return string revoked|invalid_input|not_found|already_revoked|failed */
    public function revokeSession(string $sessionId): string
    {
        if ($sessionId === '' || ! Str::isUuid($sessionId)) {
            return 'invalid_input';
        }
        $connection = $this->database->connection('mysql');
        $row = $connection->table('device_sessions')->where('id', $sessionId)->first(['revoked_at']);
        if ($row === null) {
            return 'not_found';
        }
        if ($row->revoked_at !== null) {
            return 'already_revoked';
        }

        try {
            $connection->transaction(function () use ($connection, $sessionId): void {
                $connection->table('device_sessions')->where('id', $sessionId)->update([
                    'revoked_at' => now('UTC'),
                    'updated_at' => now('UTC'),
                ]);
            });

            return 'revoked';
        } catch (Throwable) {
            return 'failed';
        }
    }
}
