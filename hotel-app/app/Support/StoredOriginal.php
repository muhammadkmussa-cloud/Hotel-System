<?php

declare(strict_types=1);

namespace App\Support;

/**
 * P08.04 — Result of a successful private-disk original write.
 *
 * This is the filesystem side only. The caller (P08.08 upload action) pairs
 * these bytes with a Media row via MediaMetadata::create using the returned
 * SHA-256 and storage path.
 */
final class StoredOriginal
{
    public function __construct(
        public readonly string $disk,
        public readonly string $storagePath,
        public readonly string $sha256,
        public readonly int $bytes,
        public readonly string $mime,
        public readonly int $width,
        public readonly int $height,
    ) {}
}
