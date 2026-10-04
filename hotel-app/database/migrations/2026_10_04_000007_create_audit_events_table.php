<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('audit_events', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('event', 64);
            // No FK: the audit trail must survive independently of staff records.
            $table->uuid('actor_staff_user_id')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->json('context')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->index(['event', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_events');
    }
};
