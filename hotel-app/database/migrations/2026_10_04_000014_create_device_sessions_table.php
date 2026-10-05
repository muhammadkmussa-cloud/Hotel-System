<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('device_sessions', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('device_id');
            $table->char('token_digest', 64)->unique();
            $table->timestamp('issued_at');
            $table->timestamp('expires_at');
            $table->timestamp('revoked_at')->nullable();
            $table->timestamp('last_seen_at')->nullable();
            $table->timestamps();
            $table->foreign('device_id')->references('id')->on('devices')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('device_sessions');
    }
};
