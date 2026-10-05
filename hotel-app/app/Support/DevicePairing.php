<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Database\DatabaseManager;
use Illuminate\Support\Str;
use Throwable;

/**
 * Short-lived device pairing codes. The raw code is shown once; only its
 * digest is stored. Activation is single-use and expires.
 */
final class DevicePairing
{
    public function __construct(private readonly DatabaseManager $database) {}

    public function issue(string $deviceId, int $ttlMinutes): string
    {
        $code = bin2hex(random_bytes(16));
        $this->database->connection('mysql')->table('device_pairing_codes')->insert([
            'id' => (string) Str::uuid7(),
            'device_id' => $deviceId,
            'code_hash' => hash('sha256', $code),
            'expires_at' => now('UTC')->addMinutes($ttlMinutes),
            'created_at' => now('UTC'),
        ]);

        return $code;
    }

    /**
     * @return array{status:string, deviceId?:string} status is activated|invalid_input|not_found|already_used|expired|failed
     */
    public function activate(string $code): array
    {
        if ($code === '') {
            return ['status' => 'invalid_input'];
        }
        $connection = $this->database->connection('mysql');
        $row = $connection->table('device_pairing_codes')->where('code_hash', hash('sha256', $code))->first();
        if ($row === null) {
            return ['status' => 'not_found'];
        }
        if ($row->used_at !== null) {
            return ['status' => 'already_used'];
        }
        if (now('UTC')->greaterThan($row->expires_at)) {
            return ['status' => 'expired'];
        }

        try {
            $affected = $connection->table('device_pairing_codes')
                ->where('id', $row->id)
                ->whereNull('used_at')
                ->update(['used_at' => now('UTC')]);
        } catch (Throwable) {
            return ['status' => 'failed'];
        }
        if ($affected === 0) {
            return ['status' => 'already_used'];
        }

        return ['status' => 'activated', 'deviceId' => (string) $row->device_id];
    }
}
