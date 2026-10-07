<?php

declare(strict_types=1);

namespace App\Domain;

use Illuminate\Support\Facades\DB;

/**
 * Append-only business audit trail, written inside the same transaction as
 * the mutation it describes. Context is scalar-only and secret-filtered.
 */
final class Audit
{
    private const FORBIDDEN = ['password', 'token', 'secret', 'cookie', 'credential', 'csrf', 'phone', 'allergy'];

    /** @param array<string, scalar|null> $context */
    public static function record(string $event, ?string $staffUserId, array $context = []): void
    {
        $safe = [];
        foreach ($context as $key => $value) {
            $name = strtolower((string) $key);
            foreach (self::FORBIDDEN as $needle) {
                if (str_contains($name, $needle)) {
                    continue 2;
                }
            }
            if (is_string($value)) {
                $value = mb_substr($value, 0, 200);
            } elseif (! is_int($value) && ! is_bool($value) && $value !== null) {
                continue;
            }
            $safe[$key] = $value;
        }
        DB::table('audit_events')->insert([
            'id' => Ids::new(),
            'event' => mb_substr($event, 0, 64),
            'actor_staff_user_id' => Ids::valid($staffUserId) ? $staffUserId : null,
            'ip_address' => request()?->ip(),
            'context' => $safe === [] ? null : json_encode($safe, JSON_THROW_ON_ERROR),
            'created_at' => now('UTC'),
        ]);
    }
}
