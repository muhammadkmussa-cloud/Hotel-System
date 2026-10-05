<?php

declare(strict_types=1);

namespace App\Support;

/**
 * P08.03 — Immutable value object returned by RasterValidator on success.
 */
final class RasterInfo
{
    public function __construct(
        public readonly string $mime,
        public readonly int $width,
        public readonly int $height,
        public readonly int $validatedBytesOffset,
    ) {}
}
