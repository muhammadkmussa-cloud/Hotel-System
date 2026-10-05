<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * P08.04 — Disk-relative storage path for the accepted original file.
 *
 * Nullable because P08.01 created the media row schema with only metadata
 * fields; after this migration, successful uploads record their
 * private-disk path (e.g. `media/originals/2610/0193ab...jpg`) here so
 * P08.05 re-encoding and P08.09 derivative delivery can find the master
 * without trusting any client-supplied name.
 *
 * The path is private: it is stored under storage/app/private/ which is
 * outside DirectAdmin public_html, and delivery to browsers happens only
 * through generated derivatives placed in public/media/ by P08.06/P08.09.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('media', function (Blueprint $table): void {
            $table->string('original_storage_path', 255)->nullable()->after('original_bytes');
            // Index by state so staff dashboard queries for pending uploads
            // don't scan the whole table.
            $table->index(['publication_state', 'created_at'], 'media_state_created_index');
        });
    }

    public function down(): void
    {
        Schema::table('media', function (Blueprint $table): void {
            $table->dropIndex('media_state_created_index');
            $table->dropColumn('original_storage_path');
        });
    }
};
