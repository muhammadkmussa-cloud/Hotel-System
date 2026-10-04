<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Contracts\Hashing\Hasher;
use InvalidArgumentException;
use RuntimeException;

/**
 * Single place for staff password hashing/verification. Plaintext is never
 * stored; the pinned Laravel hasher (bcrypt) supplies salting. Passwords are
 * annotated as sensitive so they cannot appear in exception traces.
 */
final class PasswordHasher
{
    public const MIN_LENGTH = 12;

    public const MAX_BYTES = 72;

    public function __construct(private readonly Hasher $hasher) {}

    public function hash(#[\SensitiveParameter] string $plain): string
    {
        $this->assertAcceptable($plain);

        return $this->hasher->make($plain);
    }

    public function verify(#[\SensitiveParameter] string $plain, string $hash): bool
    {
        if ($plain === '' || $hash === '') {
            return false;
        }

        try {
            return $this->hasher->check($plain, $hash);
        } catch (RuntimeException) {
            // Unknown/mismatched algorithm or malformed hash: not a valid match.
            return false;
        }
    }

    public function needsRehash(string $hash): bool
    {
        if ($hash === '') {
            return false;
        }
        try {
            return $this->hasher->needsRehash($hash);
        } catch (RuntimeException) {
            return false;
        }
    }

    private function assertAcceptable(string $plain): void
    {
        if (mb_strlen($plain) < self::MIN_LENGTH || strlen($plain) > self::MAX_BYTES || str_contains($plain, "\0")) {
            throw new InvalidArgumentException('Password does not meet the length policy.');
        }
    }
}
