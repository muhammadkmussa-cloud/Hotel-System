<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * P08.05/P08.06 — re-encoded, metadata-free public derivatives. Originals stay
 * private; only these rows point at browser-deliverable files under
 * public/media/ with unguessable server-generated names.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('media_variants', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('media_id');
            $table->string('purpose', 16); // thumb | card | hero | portrait
            $table->string('format', 8);   // webp | jpeg
            $table->unsignedSmallInteger('width');
            $table->unsignedSmallInteger('height');
            $table->unsignedInteger('bytes');
            $table->string('public_path', 190)->unique();
            $table->unsignedInteger('media_version');
            $table->timestamps();
            $table->index(['media_id', 'purpose']);
            $table->foreign('media_id')->references('id')->on('media')->cascadeOnDelete();
        });

        Schema::create('media_edits', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('media_id');
            $table->uuid('actor_staff_user_id')->nullable();
            $table->unsignedInteger('from_version');
            $table->unsignedInteger('to_version');
            $table->json('changes');
            $table->timestamp('created_at');
            $table->index(['media_id', 'created_at']);
            $table->foreign('media_id')->references('id')->on('media')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('media_edits');
        Schema::dropIfExists('media_variants');
    }
};
