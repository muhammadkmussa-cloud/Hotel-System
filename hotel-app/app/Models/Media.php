<?php

declare(strict_types=1);

namespace App\Models;

/**
 * Media asset metadata.
 *
 * The `media` table stores ownership, rights, checksum, alt text, crop focal
 * point, and publication state for every accepted meal/ingredient image.
 * Original and derivative bytes are stored on disk (P08.04–P08.06); this row
 * is the authority on who approved the asset and whether it may be published.
 *
 * @property string $id
 * @property string $kind
 * @property string|null $original_stem
 * @property string|null $original_sha256
 * @property int|null $original_bytes
 * @property string|null $original_storage_path
 * @property int|null $master_width
 * @property int|null $master_height
 * @property string|null $mime_type
 * @property string|null $rights_owner
 * @property string|null $rights_summary
 * @property string|null $rights_restriction
 * @property \Illuminate\Support\Carbon|null $rights_granted_at
 * @property string|null $alt_text
 * @property string|null $label
 * @property int $focal_x
 * @property int $focal_y
 * @property int|null $crop_x
 * @property int|null $crop_y
 * @property int|null $crop_width
 * @property int|null $crop_height
 * @property string $publication_state
 * @property string|null $content_approver_name
 * @property \Illuminate\Support\Carbon|null $content_approved_at
 * @property string|null $chef_approver_name
 * @property \Illuminate\Support\Carbon|null $chef_approved_at
 * @property int $version
 */
final class Media extends Record
{
    public const CREATED_AT = 'created_at';
    public const UPDATED_AT = 'updated_at';

    public const KIND_MEAL = 'meal';
    public const KIND_INGREDIENT = 'ingredient';
    public const KIND_PLACEHOLDER = 'placeholder';

    public const STATE_DRAFT = 'draft';
    public const STATE_REVIEWING = 'reviewing';
    public const STATE_PUBLISHED = 'published';
    public const STATE_ARCHIVED = 'archived';
    public const STATE_DEMO = 'demo';

    /**
     * Publication states in which the asset must never be delivered to a
     * customer menu. `published` alone is customer-visible; `demo` is kept
     * explicitly separate so prototype screens can show labelled demo content
     * without ever leaking into a live catalogue.
     */
    public const NON_PUBLIC_STATES = [
        self::STATE_DRAFT,
        self::STATE_REVIEWING,
        self::STATE_ARCHIVED,
    ];

    protected $table = 'media';

    protected $keyType = 'string';

    public $incrementing = false;

    /** @var array<string, string> */
    protected $casts = [
        'original_bytes' => 'integer',
        'master_width' => 'integer',
        'master_height' => 'integer',
        'focal_x' => 'integer',
        'focal_y' => 'integer',
        'crop_x' => 'integer',
        'crop_y' => 'integer',
        'crop_width' => 'integer',
        'crop_height' => 'integer',
        'version' => 'integer',
        'rights_granted_at' => 'date:Y-m-d',
        'content_approved_at' => 'date:Y-m-d',
        'chef_approved_at' => 'date:Y-m-d',
    ];

    /** @var list<string> */
    protected $guarded = ['id', 'version', 'created_at', 'updated_at'];
}
