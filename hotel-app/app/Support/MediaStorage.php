<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

/**
 * P08.04 — Quarantined original media storage.
 *
 * Writes accepted raster masters to the private Laravel disk configured in
 * config/filesystems.php (storage/app/private/), which is mapped outside the
 * DirectAdmin public_html web root. Every write:
 *
 *   1. Validates the raster again (defence in depth even if the HTTP
 *      middleware ran earlier; a CLI/direct caller must not bypass checks).
 *   2. Computes SHA-256 BEFORE touching disk. The upload action (P08.08)
 *      will detect duplicates against the media.original_sha256 unique
 *      index and abort; this class only deduplicates within-process writes
 *      and always verifies the written bytes hash back.
 *   3. Generates a server-side UUID7 filename sharded by UTC year-month.
 *      The original client stem is stored as metadata only and never
 *      appears in the storage path.
 *   4. Writes to a ".tmp.<random>" sibling first, hashes it back, then
 *      atomically renames to the final path so a crash during write never
 *      leaves a partially-written file under the permanent name.
 *   5. Records a SecurityAudit media_uploaded event.
 *
 * Security notes:
 *   - Originals live under private/media/originals/YYMM/uuid.ext, NOT under
 *     public/, so no direct URL can serve them.
 *   - Extensions are chosen from an internal allow-list mapped from the
 *     validated MIME type; client-supplied filenames are ignored for
 *     storage.
 *   - No PHP/HTML/SVG/executable content can reach this method because
 *     RasterValidator runs first and rejects anything that isn't JPEG/PNG/WebP.
 */
final class MediaStorage
{
    public const ORIGINALS_PREFIX = 'media/originals';

    /** @var array<non-empty-string, non-empty-string> */
    private const MIME_EXTENSION = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
    ];

    private ?Filesystem $diskOverride = null;

    public function __construct(
        private readonly RasterValidator $validator,
        private readonly SecurityAudit $audit,
    ) {}

    /**
     * Test hook: replace the Laravel disk with a fake/alternative
     * Filesystem implementation. Returns $this for fluent use in tests.
     */
    public function overrideDisk(Filesystem $disk): self
    {
        $this->diskOverride = $disk;
        return $this;
    }

    /**
     * Write a quarantined original. Returns StoredOriginal on success. On
     * any failure the temporary file is cleaned up and either a
     * MediaException (validation problem) or RuntimeException (storage
     * failure) is thrown.
     *
     * @param  string  $bytes  Complete file contents (already bounded by
     *                         BoundMediaUpload to MEDIA_UPLOAD_MAX_BYTES).
     * @param  string  $originalStem  Sanitised original filename stem from
     *                                the client, for metadata only.
     * @param  string  $actorId  Authenticated staff principal ID for audit.
     * @param  string  $kind  Media::KIND_* constant.
     */
    public function storeOriginal(
        string $bytes,
        string $originalStem,
        string $actorId,
        string $kind = 'meal',
    ): StoredOriginal {
        // 1. Validate raster signature, dimensions and size budget.
        $info = $this->validator->validate($bytes, $originalStem);
        $size = strlen($bytes);
        $extension = self::MIME_EXTENSION[$info->mime] ?? null;
        if ($extension === null) {
            throw MediaException::notARaster(' (unsupported mime)');
        }

        // 2. Hash-before-write (and verify again after disk write).
        $sha256 = hash('sha256', $bytes);
        if (! is_string($sha256) || strlen($sha256) !== 64 || ! ctype_xdigit($sha256)) {
            throw new \RuntimeException('Unable to compute SHA-256 of upload.');
        }

        // 3. Server-generated path, sharded by UTC year-month.
        $shard = gmdate('ym');
        $id = (string) Str::uuid7();
        $finalRelative = self::ORIGINALS_PREFIX.'/'.$shard.'/'.$id.'.'.$extension;
        $tmpRelative = $finalRelative.'.tmp.'.bin2hex(random_bytes(4));

        $disk = $this->disk();

        try {
            // Write to the temp sibling.
            $writeResult = $disk->put($tmpRelative, $bytes);
            if ($writeResult === false) {
                throw new \RuntimeException('Could not write quarantine temp file.');
            }
            // Read back and verify byte count + hash.
            $written = $disk->get($tmpRelative);
            if (! is_string($written) || strlen($written) !== $size || hash('sha256', $written) !== $sha256) {
                $disk->delete($tmpRelative);
                throw new \RuntimeException('Quarantine file hash mismatch after write.');
            }
            // Atomic rename to permanent name.
            if (! $disk->move($tmpRelative, $finalRelative)) {
                $disk->delete($tmpRelative);
                throw new \RuntimeException('Could not finalise quarantine file.');
            }
            // Final safety check — permanent file exists and is the right size.
            if (! $disk->exists($finalRelative) || $disk->size($finalRelative) !== $size) {
                $disk->delete($finalRelative);
                throw new \RuntimeException('Final quarantine file missing after move.');
            }
        } catch (Throwable $t) {
            if ($disk->exists($tmpRelative)) {
                $disk->delete($tmpRelative);
            }
            if ($disk->exists($finalRelative)) {
                $disk->delete($finalRelative);
            }
            if ($t instanceof MediaException) {
                throw $t;
            }
            throw new \RuntimeException('Failed to store upload to quarantine: '.$t->getMessage(), 0, $t);
        }

        $this->audit->record('media_uploaded', $actorId, null, [
            'kind' => $kind,
            'bytes' => $size,
            'mime' => $info->mime,
            'sha256' => $sha256,
            'width' => $info->width,
            'height' => $info->height,
            'original_stem' => mb_substr($originalStem, 0, 120),
            'path' => $finalRelative,
        ]);

        return new StoredOriginal(
            disk: self::diskName(),
            storagePath: $finalRelative,
            sha256: $sha256,
            bytes: $size,
            mime: $info->mime,
            width: $info->width,
            height: $info->height,
        );
    }

    /**
     * Permanently delete an original from quarantine. Used when an upload
     * is rolled back (duplicate DB race or metadata create failure). Safe
     * to call if the file does not exist.
     */
    public function deleteOriginal(string $storagePath): void
    {
        if (! $this->isManagedPath($storagePath)) {
            return;
        }
        $disk = $this->disk();
        if ($disk->exists($storagePath)) {
            $disk->delete($storagePath);
        }
    }

    /**
     * Absolute filesystem path for an original. Returns null when the path
     * is not under our managed prefix, or when the disk does not expose a
     * local path (defence against path traversal in any caller that
     * surfaces paths).
     */
    public function absolutePath(string $storagePath): ?string
    {
        if (! $this->isManagedPath($storagePath)) {
            return null;
        }
        $disk = $this->disk();
        if ($disk instanceof FilesystemAdapter) {
            return $disk->path($storagePath);
        }
        return null;
    }

    /**
     * Read the full bytes of an original. Used by P08.05 re-encoding and
     * by admin tools; never used by customer delivery (which serves
     * derivatives from public/media only).
     */
    public function readOriginal(string $storagePath): ?string
    {
        if (! $this->isManagedPath($storagePath)) {
            return null;
        }
        $disk = $this->disk();
        if (! $disk->exists($storagePath)) {
            return null;
        }
        $contents = $disk->get($storagePath);
        return is_string($contents) ? $contents : null;
    }

    private function isManagedPath(string $storagePath): bool
    {
        if ($storagePath === '') {
            return false;
        }
        if (! str_starts_with($storagePath, self::ORIGINALS_PREFIX.'/')) {
            return false;
        }
        // Reject path traversal attempts.
        if (str_contains($storagePath, '..') || str_contains($storagePath, "\0")) {
            return false;
        }
        return true;
    }

    private function disk(): Filesystem
    {
        if ($this->diskOverride !== null) {
            return $this->diskOverride;
        }
        return Storage::disk(self::diskName());
    }

    private static function diskName(): string
    {
        return function_exists('app') && app()->bound('config')
            ? (string) config('filesystems.default', 'local')
            : 'local';
    }
}
