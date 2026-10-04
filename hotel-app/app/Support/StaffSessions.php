<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Database\DatabaseManager;
use Illuminate\Support\Str;

/**
 * Server-side staff session registry. The browser holds only the session id;
 * the matching row can be revoked server-side so an old cookie stops working.
 */
final class StaffSessions
{
    public function __construct(private readonly DatabaseManager $database) {}

    /**
     * The server-side row has an absolute lifetime equal to the configured
     * session lifetime; it is not slid forward on activity. Idle/inactivity
     * handling is a separate, explicit concern (P05.08).
     */
    public function start(string $staffUserId, string $sessionId, int $lifetimeMinutes): string
    {
        $connection = $this->database->connection('mysql');
        $id = (string) Str::uuid7();
        $now = now('UTC');
        $connection->table('staff_sessions')->insert([
            'id' => $id,
            'staff_user_id' => $staffUserId,
            'token_hash' => $this->digest($sessionId),
            'issued_at' => $now,
            'expires_at' => $now->copy()->addMinutes($lifetimeMinutes),
            'last_seen_at' => $now,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return $id;
    }

    public function active(string $sessionRowId, string $sessionId, ?string $expectedStaffUserId = null): bool
    {
        try {
            $row = $this->database->connection('mysql')->table('staff_sessions')
                ->where('id', $sessionRowId)
                ->whereNull('revoked_at')
                ->where('expires_at', '>', now('UTC'))
                ->first(['token_hash', 'staff_user_id']);
        } catch (\Throwable) {
            return false;
        }
        if ($row === null || ! hash_equals((string) $row->token_hash, $this->digest($sessionId))) {
            return false;
        }
        if ($expectedStaffUserId !== null && ! hash_equals((string) $row->staff_user_id, $expectedStaffUserId)) {
            return false;
        }

        return true;
    }

    /** Re-bind the row to a new browser session id after the id is regenerated. */
    public function rebind(string $sessionRowId, string $sessionId): void
    {
        $now = now('UTC');
        $this->database->connection('mysql')->table('staff_sessions')
            ->where('id', $sessionRowId)
            ->update(['token_hash' => $this->digest($sessionId), 'last_seen_at' => $now, 'updated_at' => $now]);
    }

    public function touch(string $sessionRowId): void
    {
        $now = now('UTC');
        $this->database->connection('mysql')->table('staff_sessions')
            ->where('id', $sessionRowId)->update(['last_seen_at' => $now, 'updated_at' => $now]);
    }

    /** Idle seconds since the last recorded activity, or null if the row is unknown. */
    public function idleSeconds(string $sessionRowId): ?int
    {
        try {
            $seen = $this->database->connection('mysql')->table('staff_sessions')
                ->where('id', $sessionRowId)->value('last_seen_at');
        } catch (\Throwable) {
            return null;
        }
        if ($seen === null) {
            return null;
        }

        return max(0, now('UTC')->getTimestamp() - \Illuminate\Support\Carbon::parse((string) $seen, 'UTC')->getTimestamp());
    }

    public function revoke(string $sessionRowId): void
    {
        $this->database->connection('mysql')->table('staff_sessions')
            ->where('id', $sessionRowId)->whereNull('revoked_at')
            ->update(['revoked_at' => now('UTC'), 'updated_at' => now('UTC')]);
    }

    public function revokeAllForStaff(string $staffUserId): void
    {
        $this->database->connection('mysql')->table('staff_sessions')
            ->where('staff_user_id', $staffUserId)->whereNull('revoked_at')
            ->update(['revoked_at' => now('UTC'), 'updated_at' => now('UTC')]);
    }

    private function digest(string $sessionId): string
    {
        return hash('sha256', $sessionId);
    }
}
