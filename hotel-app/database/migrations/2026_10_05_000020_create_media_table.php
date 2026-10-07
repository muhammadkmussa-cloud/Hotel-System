<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * P08.01 — Media metadata: ownership, rights, checksum, alt text, crop, and
 * publication state. Original files and processed variants live outside this
 * table; this row is the authority on what the asset is, who approved it, and
 * whether it may be shown to customers. Upload/derivative processing arrives in
 * P08.02–P08.06.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('media', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            // What the asset depicts. Editors scope the asset to a usage; the
            // foreign keys to ingredients/meals are added when those tables
            // exist (P09/P10). Until then kind + owner context are enough.
            $table->enum('kind', ['meal', 'ingredient', 'placeholder'])->default('meal');
            // Stable original-filename stem is informational only; storage paths
            // are generated server-side and never trust this name.
            $table->string('original_stem', 120)->nullable();
            // SHA-256 of the accepted original file bytes. Null until the
            // upload step records an accepted original (P08.02).
            $table->char('original_sha256', 64)->when(\Illuminate\Support\Facades\Schema::getConnection()->getDriverName() === 'mysql', fn ($column) => $column->charset('binary'))->nullable();
            $table->unsignedBigInteger('original_bytes')->nullable();
            // Master pixel dimensions — recorded when a raster is decoded
            // (P08.03/P08.05). Null for placeholder records with no raster.
            $table->unsignedSmallInteger('master_width')->nullable();
            $table->unsignedSmallInteger('master_height')->nullable();
            $table->string('mime_type', 32)->nullable();
            // Rights and provenance. `rights_owner` names the person or entity
            // that granted usage; `rights_summary` is a short label (for example
            // "Hotel-commissioned, all rights reserved" or "chef supplied");
            // `rights_restriction` captures any expiry or non-transfer note.
            $table->string('rights_owner', 150)->nullable();
            $table->string('rights_summary', 200)->nullable();
            $table->string('rights_restriction', 300)->nullable();
            $table->date('rights_granted_at')->nullable();
            // Alt text and label. Alt text is required before publication so
            // screen readers announce a meaningful description; visible label
            // is optional and used only when the UI calls for a caption.
            $table->text('alt_text')->nullable();
            $table->string('label', 200)->nullable();
            // Crop/focal point as normalised 0..10000 integers (so we do not
            // carry floating-point precision issues). (fx, fy) is the focal
            // point; crop_{x,y,width,height} is an optional editor crop box
            // also in normalised coordinates. Derivatives honour this box;
            // null means "use the entire master with safe centred crop".
            $table->unsignedSmallInteger('focal_x')->default(5000);
            $table->unsignedSmallInteger('focal_y')->default(5000);
            $table->unsignedSmallInteger('crop_x')->nullable();
            $table->unsignedSmallInteger('crop_y')->nullable();
            $table->unsignedSmallInteger('crop_width')->nullable();
            $table->unsignedSmallInteger('crop_height')->nullable();
            // Publication state controls whether customer screens may ever
            // request any derivative. Draft assets are editor-visible only;
            // reviewing captures an in-progress chef/photographer approval;
            // published assets can be referenced by live meals/ingredients;
            // archived assets are removed from catalogues but remain attached
            // to historical order snapshots; demo marks labelled imagery that
            // must never be served to real customers.
            $table->enum('publication_state', [
                'draft', 'reviewing', 'published', 'archived', 'demo',
            ])->default('draft');
            // Approval identities and dates. Photographer/content approver
            // signs off that the image accurately represents the dish; chef
            // approver signs off that the content matches the real recipe.
            $table->string('content_approver_name', 150)->nullable();
            $table->date('content_approved_at')->nullable();
            $table->string('chef_approver_name', 150)->nullable();
            $table->date('chef_approved_at')->nullable();
            // Versioned metadata edits. Variants added later do not change the
            // metadata version because cropping or re-deriving does not alter
            // rights, alt text or publication state — they bump it explicitly.
            $table->unsignedInteger('version')->default(1);
            $table->timestamps();

            $table->index(['kind', 'publication_state'], 'media_kind_state_index');
            $table->unique(['original_sha256'], 'media_sha256_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('media');
    }
};
