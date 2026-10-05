<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('guest_bindings', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('guest_id');
            $table->uuid('device_session_id');
            // The staff member who authorised the binding; never the guest.
            $table->uuid('bound_by_staff_user_id')->nullable();
            $table->timestamp('bound_at');
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();
            $table->foreign('guest_id')->references('id')->on('guests')->restrictOnDelete();
            $table->foreign('device_session_id')->references('id')->on('device_sessions')->restrictOnDelete();
            $table->foreign('bound_by_staff_user_id')->references('id')->on('staff_users')->restrictOnDelete();
            // MySQL-compatible partial uniqueness: one live binding per device
            // session. Expiry is evaluated on read because a generated column
            // cannot call NOW().
            $table->unsignedTinyInteger('active_flag')->nullable()->storedAs('CASE WHEN revoked_at IS NULL THEN 1 ELSE NULL END');
            $table->unique(['device_session_id', 'active_flag'], 'guest_bindings_device_active_unique');
            $table->index(['guest_id', 'revoked_at'], 'guest_bindings_guest_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('guest_bindings');
    }
};
