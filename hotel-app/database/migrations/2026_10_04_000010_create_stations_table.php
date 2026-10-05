<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stations', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('name', 64);
            $table->enum('kind', ['kitchen', 'bar']);
            $table->boolean('active')->default(true);
            $table->json('routing')->nullable();
            $table->timestamps();
            $table->unique(['name', 'kind']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stations');
    }
};
