<?php

declare(strict_types=1);

namespace App\Support;

use DateTime;
use Illuminate\Database\DatabaseManager;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\PreconditionFailedHttpException;
use Throwable;

/**
 * Create and edit media metadata for meal and ingredient images.
 *
 * P08.01 scope: only the record of ownership, rights, checksum, alt text, crop,
 * and publication state. This service does not touch the filesystem, accept
 * uploads, or re-encode derivatives — those are P08.02–P08.09 work.
 *
 * Rights fields are free text with length caps, validated for format rather
 * than approved legal language; the actual licence decision belongs to the
 * hotel (O03/O12). Checksums and pixel dimensions are written later by the
 * upload/decoding steps and cannot be faked through the metadata editor.
 */
final class MediaMetadata
{
    public const MAX_ALT_TEXT_LENGTH = 1000;
    public const MAX_LABEL_LENGTH = 200;
    public const MAX_RIGHTS_OWNER_LENGTH = 150;
    public const MAX_RIGHTS_SUMMARY_LENGTH = 200;
    public const MAX_RIGHTS_RESTRICTION_LENGTH = 300;
    public const MAX_STEM_LENGTH = 120;
    public const FOCAL_MAX = 10000;

    public function __construct(
        private readonly DatabaseManager $database,
        private readonly SecurityAudit $audit,
    ) {}

    /**
     * Create an empty media record (draft state, no raster yet). Used by the
     * uploader to reserve a row before a file lands (P08.02) and by tests that
     * need a concrete metadata row.
     *
     * @param 'meal'|'ingredient'|'placeholder' $kind
     * @return array{result:string,id?:string,version?:int}
     */
    public function create(string $kind, string $actorId): array
    {
        if (! in_array($kind, ['meal', 'ingredient', 'placeholder'], true) || $actorId === '') {
            return ['result' => 'invalid_input'];
        }

        $id = (string) Str::uuid7();
        $now = now('UTC');

        try {
            $this->database->connection('mysql')->table('media')->insert([
                'id' => $id,
                'kind' => $kind,
                'publication_state' => 'draft',
                'focal_x' => 5000,
                'focal_y' => 5000,
                'version' => 1,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        } catch (Throwable) {
            return ['result' => 'failed'];
        }

        return ['result' => 'created', 'id' => $id, 'version' => 1];
    }

    /**
     * Update metadata fields. Rights/alt/crop/label changes bump the version;
     * pixel/checksum fields are owned by the upload/decoding steps and are
     * rejected here so a metadata editor cannot spoof accepted bytes.
     *
     * @param array<string,mixed> $changes
     * @return array{result:string,version?:int} result is updated|invalid_input|not_found|stale|failed
     */
    public function updateMetadata(string $mediaId, string $actorId, int $expectedVersion, array $changes): array
    {
        if (! Str::isUuid($mediaId) || $actorId === '' || $expectedVersion < 1) {
            return ['result' => 'invalid_input'];
        }

        $clean = $this->validateEditableFields($changes);
        if ($clean === null) {
            return ['result' => 'invalid_input'];
        }
        if ($clean === []) {
            return ['result' => 'invalid_input'];
        }

        $connection = $this->database->connection('mysql');
        $row = $connection->table('media')->where('id', $mediaId)->first(['id', 'version']);
        if ($row === null) {
            return ['result' => 'not_found'];
        }

        try {
            VersionedUpdate::apply($connection, 'media', $mediaId, $expectedVersion, $clean, 'version');

            $this->audit->record('media_metadata_edited', $actorId, null, [
                'media_id' => $mediaId,
            ]);

            return ['result' => 'updated', 'version' => $expectedVersion + 1];
        } catch (PreconditionFailedHttpException) {
            return ['result' => 'stale'];
        } catch (Throwable) {
            return ['result' => 'failed'];
        }
    }

    /**
     * Transition publication state. Publication to `published` requires alt
     * text; moving to `demo` clears any accidental publication; archiving is
     * allowed from any non-archived state.
     *
     * @return array{result:string,version?:int}
     */
    public function transitionState(string $mediaId, string $actorId, int $expectedVersion, string $targetState): array
    {
        if (! Str::isUuid($mediaId) || $actorId === '' || $expectedVersion < 1) {
            return ['result' => 'invalid_input'];
        }
        if (! in_array($targetState, ['draft', 'reviewing', 'published', 'archived', 'demo'], true)) {
            return ['result' => 'invalid_input'];
        }

        $connection = $this->database->connection('mysql');
        $row = $connection->table('media')->where('id', $mediaId)->first(['id', 'version', 'alt_text', 'publication_state']);
        if ($row === null) {
            return ['result' => 'not_found'];
        }
        if ((int) $row->version !== $expectedVersion) {
            return ['result' => 'stale'];
        }
        if ((string) $row->publication_state === $targetState) {
            return ['result' => 'updated', 'version' => $expectedVersion];
        }
        // Publishing requires alt text so a screen reader never encounters an
        // image with no accessible name. The check does not verify quality;
        // that is a human review gate (P27).
        if ($targetState === 'published' && ($row->alt_text === null || trim((string) $row->alt_text) === '')) {
            return ['result' => 'alt_text_required'];
        }

        try {
            VersionedUpdate::apply($connection, 'media', $mediaId, $expectedVersion, [
                'publication_state' => $targetState,
            ], 'version');

            $this->audit->record('media_publication_changed', $actorId, null, [
                'media_id' => $mediaId,
                'to_state' => $targetState,
            ]);

            return ['result' => 'updated', 'version' => $expectedVersion + 1];
        } catch (PreconditionFailedHttpException) {
            return ['result' => 'stale'];
        } catch (Throwable) {
            return ['result' => 'failed'];
        }
    }

    /** @return array{id:string,kind:string,publication_state:string,version:int}|null */
    public function find(string $mediaId): ?array
    {
        if (! Str::isUuid($mediaId)) {
            return null;
        }
        $row = $this->database->connection('mysql')->table('media')->where('id', $mediaId)
            ->first(['id', 'kind', 'publication_state', 'version']);
        if ($row === null) {
            return null;
        }

        return [
            'id' => (string) $row->id,
            'kind' => (string) $row->kind,
            'publication_state' => (string) $row->publication_state,
            'version' => (int) $row->version,
        ];
    }

    /**
     * @return ?array<string,mixed> Cleaned changes, null on invalid input, or
     * an empty array when there is nothing to write.
     */
    private function validateEditableFields(array $changes): ?array
    {
        $allowed = [
            'label', 'alt_text',
            'rights_owner', 'rights_summary', 'rights_restriction', 'rights_granted_at',
            'focal_x', 'focal_y', 'crop_x', 'crop_y', 'crop_width', 'crop_height',
        ];
        $clean = [];
        foreach ($allowed as $field) {
            if (! array_key_exists($field, $changes)) {
                continue;
            }
            $value = $changes[$field];
            switch ($field) {
                case 'label':
                    if ($value !== null) {
                        $value = trim((string) $value);
                        if ($value !== '' && mb_strlen($value) > self::MAX_LABEL_LENGTH) {
                            return null;
                        }
                        if ($value === '') {
                            $value = null;
                        }
                    }
                    break;
                case 'alt_text':
                    if ($value !== null) {
                        $value = trim((string) $value);
                        if ($value !== '' && mb_strlen($value) > self::MAX_ALT_TEXT_LENGTH) {
                            return null;
                        }
                        if ($value === '') {
                            $value = null;
                        }
                    }
                    break;
                case 'rights_owner':
                case 'rights_summary':
                case 'rights_restriction':
                    $max = match ($field) {
                        'rights_owner' => self::MAX_RIGHTS_OWNER_LENGTH,
                        'rights_summary' => self::MAX_RIGHTS_SUMMARY_LENGTH,
                        default => self::MAX_RIGHTS_RESTRICTION_LENGTH,
                    };
                    if ($value !== null) {
                        $value = trim((string) $value);
                        if ($value !== '' && mb_strlen($value) > $max) {
                            return null;
                        }
                        if ($value === '') {
                            $value = null;
                        }
                    }
                    break;
                case 'rights_granted_at':
                    if ($value !== null) {
                        $value = trim((string) $value);
                        $dt = DateTime::createFromFormat('Y-m-d', $value);
                        if ($dt === false || $dt->format('Y-m-d') !== $value) {
                            return null;
                        }
                    }
                    break;
                case 'focal_x':
                case 'focal_y':
                    if ($value === null) {
                        return null; // focal point always required
                    }
                    $value = (int) $value;
                    if ($value < 0 || $value > self::FOCAL_MAX) {
                        return null;
                    }
                    break;
                case 'crop_x':
                case 'crop_y':
                case 'crop_width':
                case 'crop_height':
                    if ($value !== null) {
                        $value = (int) $value;
                        if ($value < 0 || $value > self::FOCAL_MAX) {
                            return null;
                        }
                    }
                    break;
            }
            $clean[$field] = $value;
        }

        // If a crop box is supplied, all four values must be present and the
        // box must fit inside the normalised unit square.
        $cropFields = ['crop_x', 'crop_y', 'crop_width', 'crop_height'];
        $present = 0;
        foreach ($cropFields as $k) {
            if (array_key_exists($k, $clean) && $clean[$k] !== null) {
                $present++;
            }
        }
        if ($present > 0 && $present < 4) {
            return null;
        }
        if ($present === 4) {
            $x = $clean['crop_x']; $y = $clean['crop_y'];
            $w = $clean['crop_width']; $h = $clean['crop_height'];
            if ($w === 0 || $h === 0 || $x + $w > self::FOCAL_MAX || $y + $h > self::FOCAL_MAX) {
                return null;
            }
        }

        // Reject any protected (byte/dimension/state) fields being edited here.
        foreach (['original_sha256','original_bytes','master_width','master_height',
                  'mime_type','original_stem','publication_state',
                  'content_approver_name','content_approved_at',
                  'chef_approver_name','chef_approved_at'] as $guarded) {
            if (array_key_exists($guarded, $changes)) {
                return null;
            }
        }

        // An edit with no recognised editable field is not an edit.
        return $clean === [] ? null : $clean;
    }
}
