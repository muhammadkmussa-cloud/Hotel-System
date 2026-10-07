<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Domain\DomainError;
use App\Domain\Ids;
use Illuminate\Http\Request;

/** Small strict readers for JSON bodies. */
final class Input
{
    /** @param list<string> $allowed */
    public static function body(Request $request, array $allowed): array
    {
        $body = $request->json()->all();
        foreach (array_keys($body) as $key) {
            if (! is_string($key) || ! in_array($key, $allowed, true)) {
                throw new DomainError('VALIDATION_FAILED', 'The request contains an unexpected field.', 422);
            }
        }

        return $body;
    }

    public static function str(array $body, string $key, bool $required = true, int $max = 500): ?string
    {
        $v = $body[$key] ?? null;
        if ($v === null || $v === '') {
            if ($required) {
                throw new DomainError('VALIDATION_FAILED', 'Missing '.$key.'.', 422);
            }

            return null;
        }
        if (! is_string($v) || mb_strlen($v) > $max) {
            throw new DomainError('VALIDATION_FAILED', 'Invalid '.$key.'.', 422);
        }

        return $v;
    }

    public static function id(array $body, string $key, bool $required = true): ?string
    {
        $v = self::str($body, $key, $required, 64);
        if ($v !== null && ! Ids::valid($v)) {
            throw new DomainError('VALIDATION_FAILED', 'Invalid '.$key.'.', 422);
        }

        return $v;
    }

    public static function int(array $body, string $key, ?int $default = null): int
    {
        $v = $body[$key] ?? $default;
        if (! is_int($v)) {
            throw new DomainError('VALIDATION_FAILED', 'Invalid '.$key.'.', 422);
        }

        return $v;
    }

    /** @return list<string> */
    public static function ids(array $body, string $key): array
    {
        $v = $body[$key] ?? [];
        if (! is_array($v) || ! array_is_list($v) || count($v) > 50) {
            throw new DomainError('VALIDATION_FAILED', 'Invalid '.$key.'.', 422);
        }
        foreach ($v as $id) {
            if (! Ids::valid($id)) {
                throw new DomainError('VALIDATION_FAILED', 'Invalid '.$key.'.', 422);
            }
        }

        return $v;
    }

    public static function bool(array $body, string $key): bool
    {
        return ($body[$key] ?? false) === true;
    }
}
