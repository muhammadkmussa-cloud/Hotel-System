<?php

declare(strict_types=1);

namespace App\Domain\Media;

use App\Domain\Audit;
use App\Domain\DomainError;
use App\Domain\Ids;
use App\Support\MediaException;
use App\Support\MediaMetadata;
use App\Support\MediaStorage;
use GdImage;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Throwable;

/**
 * P08.05–P08.09 — accepted originals become re-encoded, metadata-free public
 * derivatives. Browsers only ever receive derivatives under /media/v/ with
 * unguessable names; originals stay in private storage.
 */
final class MediaLibrary
{
    /** purpose => [width, height] (cover crop around the focal point). */
    public const VARIANTS = [
        'thumb' => [160, 160],
        'portrait' => [320, 320],
        'card' => [720, 540],
        'hero' => [1280, 960],
    ];

    public const PLACEHOLDERS = [
        'meal' => '/assets/img/placeholder-meal.svg',
        'ingredient' => '/assets/img/placeholder-ingredient.svg',
    ];

    public function __construct(
        private readonly MediaStorage $storage,
        private readonly MediaMetadata $metadata,
    ) {}

    /**
     * Store a validated upload. Identical bytes reuse the existing media row
     * (one image, many dishes) instead of creating a duplicate upload.
     *
     * @return array{id:string,duplicate:bool}
     */
    public function upload(string $bytes, string $originalName, string $kind, string $actorId, bool $demo = false): array
    {
        if (! in_array($kind, ['meal', 'ingredient'], true)) {
            throw DomainError::invalid('Choose whether the photo shows a meal or an ingredient.');
        }
        // Fail before storing anything if this host cannot encode derivatives.
        $this->hostEncoders();
        $sha = hash('sha256', $bytes);
        $existing = DB::table('media')->where('original_sha256', $sha)->value('id');
        if (is_string($existing)) {
            return ['id' => $existing, 'duplicate' => true];
        }
        try {
            $stored = $this->storage->storeOriginal($bytes, mb_substr($originalName, 0, 120), $actorId, $kind);
        } catch (MediaException $error) {
            throw DomainError::invalid($error->getMessage());
        }
        $id = Ids::new();
        $now = now('UTC');
        $stem = mb_substr(pathinfo($originalName, PATHINFO_FILENAME), 0, 120);
        try {
            DB::table('media')->insert([
                'id' => $id, 'kind' => $kind, 'original_stem' => $stem,
                'original_sha256' => $stored->sha256, 'original_bytes' => $stored->bytes,
                'original_storage_path' => $stored->storagePath,
                'master_width' => $stored->width, 'master_height' => $stored->height,
                'mime_type' => $stored->mime,
                'publication_state' => $demo ? 'demo' : 'draft',
                'label' => $stem !== '' ? $stem : null,
                'focal_x' => 5000, 'focal_y' => 5000, 'version' => 1,
                'created_at' => $now, 'updated_at' => $now,
            ]);
        } catch (Throwable $error) {
            $this->storage->deleteOriginal($stored->storagePath);
            $winner = DB::table('media')->where('original_sha256', $sha)->value('id');
            if (is_string($winner)) {
                return ['id' => $winner, 'duplicate' => true];
            }
            throw $error;
        }
        try {
            $this->generateVariants($id);
        } catch (Throwable $error) {
            // A failed derivation leaves the asset in draft with no public
            // variants: it can never appear published by accident.
            report($error);
        }

        return ['id' => $id, 'duplicate' => false];
    }

    /** Re-encode every purpose from the private original. Strips all metadata. */
    public function generateVariants(string $mediaId): void
    {
        if (! function_exists('imagecreatefromstring')) {
            throw new RuntimeException('GD is required to derive images on this host.');
        }
        $this->hostEncoders();
        $media = DB::table('media')->where('id', $mediaId)->first();
        if ($media === null || $media->original_storage_path === null) {
            throw DomainError::notFound('Image not found.');
        }
        $bytes = $this->storage->readOriginal((string) $media->original_storage_path);
        if (! is_string($bytes)) {
            throw new RuntimeException('Original image is missing from private storage.');
        }
        $source = @imagecreatefromstring($bytes);
        if (! $source instanceof GdImage) {
            throw DomainError::invalid('The image could not be decoded.');
        }
        $source = $this->orient($source, $bytes, (string) $media->mime_type);
        $sw = imagesx($source);
        $sh = imagesy($source);
        // Optional editor crop box in 0..10000 normalised units.
        $box = [0, 0, $sw, $sh];
        if ($media->crop_width !== null && $media->crop_height !== null) {
            $box = [
                intdiv((int) $media->crop_x * $sw, 10000), intdiv((int) $media->crop_y * $sh, 10000),
                max(1, intdiv((int) $media->crop_width * $sw, 10000)), max(1, intdiv((int) $media->crop_height * $sh, 10000)),
            ];
        }
        $fx = (int) $media->focal_x / 10000;
        $fy = (int) $media->focal_y / 10000;

        $publicRoot = public_path('media/v');
        $old = DB::table('media_variants')->where('media_id', $mediaId)->pluck('public_path')->all();
        $rows = [];
        foreach (self::VARIANTS as $purpose => [$tw, $th]) {
            [$bx, $by, $bw, $bh] = $box;
            $targetRatio = $tw / $th;
            if ($bw / $bh > $targetRatio) {
                $cw = (int) round($bh * $targetRatio);
                $ch = $bh;
            } else {
                $cw = $bw;
                $ch = (int) round($bw / $targetRatio);
            }
            $cx = (int) max($bx, min($bx + $bw - $cw, $bx + $fx * $bw - $cw / 2));
            $cy = (int) max($by, min($by + $bh - $ch, $by + $fy * $bh - $ch / 2));
            // Never upscale beyond the master: smaller sources yield smaller variants.
            $scale = min(1.0, $tw / max(1, $cw));
            $ow = max(1, (int) round($cw * $scale));
            $oh = max(1, (int) round($ch * $scale));
            $canvas = imagecreatetruecolor($ow, $oh);
            imagealphablending($canvas, false);
            imagesavealpha($canvas, true);
            imagecopyresampled($canvas, $source, 0, 0, $cx, $cy, $ow, $oh, $cw, $ch);
            foreach ($this->formatsFor($purpose) as $format) {
                ob_start();
                $ok = $format === 'webp' ? imagewebp($canvas, null, 80) : imagejpeg($canvas, null, 82);
                $encoded = (string) ob_get_clean();
                if (! $ok || $encoded === '') {
                    continue;
                }
                $name = bin2hex(random_bytes(16)).'.'.($format === 'webp' ? 'webp' : 'jpg');
                $relative = substr($name, 0, 2).'/'.$name;
                $absolute = $publicRoot.'/'.$relative;
                if (! is_dir(dirname($absolute)) && ! mkdir(dirname($absolute), 0755, true) && ! is_dir(dirname($absolute))) {
                    throw new RuntimeException('Cannot create the public media directory.');
                }
                file_put_contents($absolute.'.tmp', $encoded);
                rename($absolute.'.tmp', $absolute);
                $rows[] = [
                    'id' => Ids::new(), 'media_id' => $mediaId, 'purpose' => $purpose, 'format' => $format,
                    'width' => $ow, 'height' => $oh, 'bytes' => strlen($encoded),
                    'public_path' => 'media/v/'.$relative, 'media_version' => (int) $media->version,
                    'created_at' => now('UTC'), 'updated_at' => now('UTC'),
                ];
            }
            imagedestroy($canvas);
        }
        imagedestroy($source);
        DB::transaction(function () use ($mediaId, $rows): void {
            DB::table('media_variants')->where('media_id', $mediaId)->delete();
            DB::table('media_variants')->insert($rows);
        });
        foreach ($old as $path) {
            $file = public_path($path);
            if (str_starts_with(realpath(dirname($file)) ?: '', realpath($publicRoot) ?: '#') && is_file($file)) {
                @unlink($file);
            }
        }
    }

    /** @return list<string> */
    private function formatsFor(string $purpose): array
    {
        $available = $this->hostEncoders();
        $wanted = in_array($purpose, ['card', 'hero'], true) ? ['webp', 'jpeg'] : ['webp'];

        return array_values(array_filter($wanted, static fn (string $format): bool => in_array($format, $available, true)));
    }

    /**
     * The image encoders this host can actually write. A missing encoder is a
     * configuration error the operator must see, not a silent draft asset.
     *
     * @return list<string>
     */
    private function hostEncoders(): array
    {
        if (! function_exists('imagetypes')) {
            throw DomainError::invalid('This host has no GD image support; images cannot be processed.');
        }
        $types = imagetypes();
        $available = [];
        if (($types & IMG_WEBP) !== 0) {
            $available[] = 'webp';
        }
        if (($types & IMG_JPG) !== 0) {
            $available[] = 'jpeg';
        }
        if ($available === []) {
            throw DomainError::invalid('This host has no supported image encoder (WebP or JPEG).');
        }

        return $available;
    }

    private function orient(GdImage $image, string $bytes, string $mime): GdImage
    {
        if ($mime !== 'image/jpeg' || ! function_exists('exif_read_data')) {
            return $image;
        }
        $exif = @exif_read_data('data://image/jpeg;base64,'.base64_encode($bytes));
        $rotation = match ((int) ($exif['Orientation'] ?? 1)) {
            3 => 180, 6 => -90, 8 => 90, default => 0,
        };

        return $rotation === 0 ? $image : (imagerotate($image, $rotation, 0) ?: $image);
    }

    /**
     * Public URL for a purpose, or a safe placeholder. Draft/archived images
     * are never served on customer surfaces ($customer = true).
     *
     * @return array{src:string,webp:?string,width:int,height:int,alt:string,demo:bool,placeholder:bool}
     */
    public function image(?string $mediaId, string $purpose, string $kind = 'meal', bool $customer = true): array
    {
        $fallback = ['src' => self::PLACEHOLDERS[$kind] ?? self::PLACEHOLDERS['meal'], 'webp' => null,
            'width' => self::VARIANTS[$purpose][0] ?? 320, 'height' => self::VARIANTS[$purpose][1] ?? 320,
            'alt' => '', 'demo' => false, 'placeholder' => true];
        if ($mediaId === null) {
            return $fallback;
        }
        $media = self::mediaRow($mediaId);
        if ($media === null || ($customer && ! in_array($media->publication_state, ['published', 'demo'], true))) {
            return $fallback;
        }
        $variants = self::variantRows($mediaId);
        $webp = $variants[$purpose]['webp'] ?? null;
        $jpeg = $variants[$purpose]['jpeg'] ?? null;
        $primary = $jpeg ?? $webp;
        if ($primary === null) {
            return $fallback;
        }

        return [
            'src' => '/'.$primary->public_path,
            'webp' => $webp !== null && $jpeg !== null ? '/'.$webp->public_path : null,
            'width' => (int) $primary->width, 'height' => (int) $primary->height,
            'alt' => (string) ($media->alt_text ?? ''),
            'demo' => $media->publication_state === 'demo', 'placeholder' => false,
        ];
    }

    /** @var array<string, ?object> */
    private static array $mediaCache = [];

    /** @var array<string, array<string, array<string, object>>> */
    private static array $variantCache = [];

    private static function mediaRow(string $id): ?object
    {
        return self::$mediaCache[$id] ??= DB::table('media')->where('id', $id)->first(['id', 'publication_state', 'alt_text', 'kind']);
    }

    /** @return array<string, array<string, object>> */
    private static function variantRows(string $id): array
    {
        if (! isset(self::$variantCache[$id])) {
            $out = [];
            foreach (DB::table('media_variants')->where('media_id', $id)->get() as $row) {
                $out[$row->purpose][$row->format] = $row;
            }
            self::$variantCache[$id] = $out;
        }

        return self::$variantCache[$id];
    }

    public static function flush(): void
    {
        self::$mediaCache = [];
        self::$variantCache = [];
    }

    /**
     * P08.07 — versioned metadata edit with a who/what history row.
     *
     * @param array<string,mixed> $changes
     */
    public function edit(string $mediaId, int $expectedVersion, array $changes, string $actorId): int
    {
        $before = DB::table('media')->where('id', $mediaId)->first();
        if ($before === null) {
            throw DomainError::notFound('Image not found.');
        }
        $result = $this->metadata->updateMetadata($mediaId, $actorId, $expectedVersion, $changes);
        if (($result['result'] ?? '') === 'stale') {
            throw new DomainError('VERSION_CONFLICT', 'Someone else changed this image. Reload and try again.', 412);
        }
        if (($result['result'] ?? '') !== 'updated') {
            throw DomainError::invalid('Check the image details: alt text, rights and crop values must be valid.');
        }
        $diff = [];
        foreach ($changes as $field => $value) {
            if (property_exists($before, $field) && (string) ($before->{$field} ?? '') !== (string) ($value ?? '')) {
                $diff[$field] = ['from' => $before->{$field}, 'to' => $value];
            }
        }
        DB::table('media_edits')->insert([
            'id' => Ids::new(), 'media_id' => $mediaId, 'actor_staff_user_id' => $actorId,
            'from_version' => $expectedVersion, 'to_version' => (int) $result['version'],
            'changes' => json_encode($diff, JSON_THROW_ON_ERROR), 'created_at' => now('UTC'),
        ]);
        if (array_intersect(array_keys($diff), ['focal_x', 'focal_y', 'crop_x', 'crop_y', 'crop_width', 'crop_height']) !== []) {
            $this->generateVariants($mediaId);
        }
        self::flush();

        return (int) $result['version'];
    }

    public function setState(string $mediaId, string $state, string $actorId): void
    {
        $media = DB::table('media')->where('id', $mediaId)->first(['version', 'publication_state']);
        if ($media === null) {
            throw DomainError::notFound('Image not found.');
        }
        if ($state === 'published' && ! DB::table('media_variants')->where('media_id', $mediaId)->exists()) {
            throw DomainError::conflict('NO_DERIVATIVES', 'This image has no processed versions yet, so it cannot be published.');
        }
        $result = $this->metadata->transitionState($mediaId, $actorId, (int) $media->version, $state);
        if (($result['result'] ?? '') !== 'updated' && ($result['result'] ?? '') !== 'transitioned') {
            throw DomainError::conflict('MEDIA_STATE', $state === 'published'
                ? 'Add alt text (and approvals) before publishing this image.'
                : 'That image state change is not allowed.');
        }
        Audit::record('media_state_changed', $actorId, ['media_id' => $mediaId, 'to' => $state]);
        self::flush();
    }
}
