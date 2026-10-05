<?php

declare(strict_types=1);

namespace App\Support;

/**
 * P08.02 — Read-only media upload configuration.
 *
 * Centralises upload limits so middleware, controllers and future validation
 * share one source of truth.
 */
final class MediaUploadLimits
{
    /**
     * Hard ceiling for an original filename stem accepted from the client.
     * Storage paths are always server-generated; this is purely to bound the
     * metadata column (see MediaMetadata::MAX_STEM_LENGTH).
     */
    public const MAX_ORIGINAL_STEM_LENGTH = 120;

    /** @return positive-int */
    public function maxBytes(): int
    {
        $value = (int) config('media.upload.max_bytes', 20 * 1024 * 1024);
        if ($value < 1) {
            return 20 * 1024 * 1024;
        }

        return $value;
    }

    /** @return non-empty-string */
    public function fieldName(): string
    {
        $value = (string) config('media.upload.field_name', 'file');

        return $value === '' ? 'file' : $value;
    }

    /** @return non-empty-list<non-empty-string> */
    public function acceptedMimeTypes(): array
    {
        $default = ['image/jpeg', 'image/png', 'image/webp'];
        $configured = config('media.upload.accepted_mime_types');
        if (! is_array($configured) || $configured === []) {
            return $default;
        }
        $clean = [];
        foreach ($configured as $type) {
            if (is_string($type) && $type !== '') {
                $clean[] = strtolower($type);
            }
        }

        return $clean === [] ? $default : $clean;
    }
}
