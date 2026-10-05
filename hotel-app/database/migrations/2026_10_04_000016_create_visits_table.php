<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('visits', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('table_id');
            $table->enum('state', ['open', 'closed'])->default('open');
            // One open visit per table: the key equals table_id while open and
            // NULL once closed, and MySQL unique indexes treat NULLs as distinct.
            $table->string('active_key', 36)->nullable()->storedAs('CASE WHEN state = "open" THEN table_id ELSE NULL END');
            $table->unique('active_key');
            $table->timestamp('opened_at')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->timestamps();
            $table->foreign('table_id')->references('id')->on('tables')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('visits');
    }
};
