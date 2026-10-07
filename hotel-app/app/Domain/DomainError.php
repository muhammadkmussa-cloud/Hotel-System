<?php

declare(strict_types=1);

namespace App\Domain;

use RuntimeException;

/**
 * A rule-based refusal with a stable code and a message safe to show the
 * person who attempted the action. Never carries private details.
 */
final class DomainError extends RuntimeException
{
    public function __construct(
        public readonly string $errorCode,
        string $message,
        public readonly int $status = 409,
        /** @var array<string,mixed> */
        public readonly array $details = [],
    ) {
        parent::__construct($message);
    }

    public static function notFound(string $message = 'That record was not found.'): self
    {
        return new self('NOT_FOUND', $message, 404);
    }

    public static function forbidden(string $message = 'This action is not permitted.'): self
    {
        return new self('FORBIDDEN', $message, 403);
    }

    public static function invalid(string $message): self
    {
        return new self('VALIDATION_FAILED', $message, 422);
    }

    public static function conflict(string $code, string $message): self
    {
        return new self($code, $message, 409);
    }
}
