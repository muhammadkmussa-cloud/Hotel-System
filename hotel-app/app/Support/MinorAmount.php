<?php

declare(strict_types=1);

namespace App\Support;

use InvalidArgumentException;

/** A non-negative KES amount already expressed in integer minor units. */
final readonly class MinorAmount
{
    private function __construct(public int $value) {}

    public static function fromMinor(mixed $input): self
    {
        if (is_int($input) && $input >= 0) {
            return new self($input);
        }

        // Compare digit strings before casting: oversized values must never
        // overflow, clamp, or pass through floating-point conversion.
        $maximum = (string) PHP_INT_MAX;
        if (is_string($input)
            && strlen($input) <= strlen($maximum)
            && preg_match('/\A(?:0|[1-9][0-9]*)\z/', $input) === 1
            && (strlen($input) < strlen($maximum) || strcmp($input, $maximum) <= 0)
        ) {
            return new self((int) $input);
        }

        throw new InvalidArgumentException('Amount must be non-negative integer minor units within the supported range.');
    }
}
