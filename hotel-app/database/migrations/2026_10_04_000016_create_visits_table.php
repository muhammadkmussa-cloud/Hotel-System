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
            $table->timestamp('opened_at')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->unsignedInteger('version')->default(1);
            $table->timestamps();
            // MySQL-compatible partial uniqueness: only one open visit per table.
            $table->unsignedTinyInteger('active_flag')->nullable()->storedAs('CASE WHEN state = "open" THEN 1 ELSE NULL END');
            $table->unique(['table_id', 'active_flag'], 'visits_table_active_unique');
            $table->foreign('table_id')->references('id')->on('tables')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('visits');
    }
};
