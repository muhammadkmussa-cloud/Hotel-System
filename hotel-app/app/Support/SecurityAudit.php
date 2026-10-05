<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Database\DatabaseManager;
use Illuminate\Support\Str;
use Throwable;

/**
 * Persists security audit events. Never stores passwords, session tokens,
 * secrets, or full personal data; auditing failures never block authentication.
 */
final class SecurityAudit
{
    private const ALLOWED_EVENTS = [
        'login_succeeded', 'login_failed', 'logout', 'unlock_succeeded', 'unlock_failed',
        'owner_bootstrapped', 'installation_configured', 'session_revoked', 'staff_deactivated', 'staff_activated', 'printer_destination_created', 'printer_destination_deactivated', 'device_revoked',
    ];

    private const FORBIDDEN_KEY_NEEDLES = ['password', 'token', 'secret', 'session', 'cookie', 'auth', 'credential', 'csrf'];

    /** Bound repeated failed-login auditing per IP so the trail cannot be flooded. */
    private const MAX_FAILED_LOGIN_PER_WINDOW = 20;

    private const FAILED_LOGIN_WINDOW_SECONDS = 60;

    public function __construct(private readonly DatabaseManager $database) {}

    /** @param array<string, scalar|null> $context */
    public function record(string $event, ?string $staffUserId = null, ?string $ipAddress = null, array $context = []): void
    {
        if (! in_array($event, self::ALLOWED_EVENTS, true)) {
            return;
        }

        if ($event === 'login_failed' && $this->failedLoginsExceeded($ipAddress)) {
            return;
        }

        $safe = [];
        foreach ($context as $key => $value) {
            // Normalise the key and reject anything resembling a credential.
            $name = preg_replace('/[^a-z0-9]/', '', strtolower((string) $key)) ?? '';
            foreach (self::FORBIDDEN_KEY_NEEDLES as $needle) {
                if (str_contains($name, $needle)) {
                    continue 2;
                }
            }
            if (is_string($value)) {
                // Reject values that look like credentials regardless of the key name.
                if (preg_match('/(password|token|secret|session|cookie=|bearer\s|authorization)/i', $value) === 1) {
                    continue;
                }
                $value = mb_substr($value, 0, 100);
            } elseif (! is_int($value) && ! is_float($value) && ! is_bool($value) && $value !== null) {
                continue;
            }
            $safe[$key] = $value;
        }

        try {
            $this->database->connection('mysql')->table('audit_events')->insert([
                'id' => (string) Str::uuid7(),
                'event' => $event,
                'actor_staff_user_id' => $staffUserId !== '' ? $staffUserId : null,
                'ip_address' => $ipAddress !== null ? mb_substr($ipAddress, 0, 45) : null,
                'context' => $safe === [] ? null : json_encode($safe, JSON_THROW_ON_ERROR),
                'created_at' => now('UTC'),
            ]);
        } catch (Throwable) {
            // Audit availability must never break authentication flows.
        }
    }

    private function failedLoginsExceeded(?string $ipAddress): bool
    {
        if ($ipAddress === null || $ipAddress === '') {
            return false;
        }

        try {
            $recent = $this->database->connection('mysql')->table('audit_events')
                ->where('event', 'login_failed')
                ->where('ip_address', $ipAddress)
                ->where('created_at', '>', now('UTC')->subSeconds(self::FAILED_LOGIN_WINDOW_SECONDS))
                ->count();
        } catch (Throwable) {
            return false;
        }

        return $recent >= self::MAX_FAILED_LOGIN_PER_WINDOW;
    }
}
