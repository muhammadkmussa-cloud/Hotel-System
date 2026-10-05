<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('devices', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('name', 64);
            $table->enum('mode', ['tablet', 'kiosk', 'cashier', 'kitchen', 'collection']);
            // Only a digest is stored; the raw credential is never persisted.
            $table->char('credential_digest', 64);
            $table->boolean('active')->default(true);
            $table->timestamp('last_seen_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('devices');
    }
};
