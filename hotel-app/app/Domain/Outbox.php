<?php

declare(strict_types=1);

namespace App\Domain;

use Illuminate\Support\Facades\DB;

/**
 * Committed change notifications for short polling (P15). Events carry only
 * identifiers and states; clients refetch authorised snapshots.
 */
final class Outbox
{
    /** @param array<string, scalar|null> $payload */
    public static function emit(string $topic, string $scope, array $payload = []): void
    {
        DB::table('outbox_events')->insert([
            'topic' => $topic,
            'scope' => $scope,
            'payload' => json_encode($payload, JSON_THROW_ON_ERROR),
            'created_at' => now('UTC'),
        ]);
    }

    public static function latestId(): int
    {
        return (int) DB::table('outbox_events')->max('id');
    }

    /**
     * @param list<string> $scopes
     * @return array{events:list<array<string,mixed>>,cursor:int,reload:bool}
     */
    public static function since(int $cursor, array $scopes, int $limit = 100): array
    {
        $latest = self::latestId();
        // A cursor ahead of the log (restore) or very far behind forces a snapshot reload.
        if ($cursor > $latest || ($cursor > 0 && $latest - $cursor > 5000)) {
            return ['events' => [], 'cursor' => $latest, 'reload' => true];
        }
        $rows = DB::table('outbox_events')->where('id', '>', $cursor)->whereIn('scope', $scopes)
            ->orderBy('id')->limit($limit)->get(['id', 'topic', 'scope', 'payload']);
        $events = [];
        $next = $cursor;
        foreach ($rows as $row) {
            $events[] = ['id' => (int) $row->id, 'topic' => $row->topic, 'scope' => $row->scope, 'payload' => json_decode((string) $row->payload, true)];
            $next = (int) $row->id;
        }
        if (count($rows) < $limit) {
            $next = max($next, $latest);
        }

        return ['events' => $events, 'cursor' => $next, 'reload' => false];
    }
}
