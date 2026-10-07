<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('roles', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('key', 64)->unique();
            $table->string('name', 100);
            $table->string('description', 255)->nullable();
            $table->timestamps();
        });

        Schema::create('staff_users', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            // Unique identity: the utf8mb4_unicode_ci collation makes this case-insensitive.
            $table->string('email', 190)->unique();
            $table->string('name', 150);
            $table->string('password_hash', 255);
            $table->boolean('active')->default(true);
            $table->timestamp('deactivated_at')->nullable();
            $table->timestamps();
        });

        Schema::create('staff_role_grants', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('staff_user_id');
            $table->uuid('role_id');
            $table->uuid('granted_by')->nullable();
            $table->timestamp('granted_at')->nullable();
            $table->timestamps();
            $table->unique(['staff_user_id', 'role_id']);
            // Restrict deletes so referenced identity history is preserved.
            $table->foreign('staff_user_id')->references('id')->on('staff_users')->restrictOnDelete();
            $table->foreign('role_id')->references('id')->on('roles')->restrictOnDelete();
            $table->foreign('granted_by')->references('id')->on('staff_users')->restrictOnDelete();
        });

        Schema::create('staff_sessions', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('staff_user_id');
            $table->char('token_hash', 64)->when(\Illuminate\Support\Facades\Schema::getConnection()->getDriverName() === 'mysql', fn ($column) => $column->charset('ascii')->collation('ascii_bin'))->unique();
            $table->timestamp('issued_at');
            $table->timestamp('expires_at');
            $table->timestamp('revoked_at')->nullable();
            $table->timestamp('last_seen_at')->nullable();
            $table->timestamps();
            $table->foreign('staff_user_id')->references('id')->on('staff_users')->restrictOnDelete();
            $table->index(['staff_user_id', 'expires_at']);
        });

        // Only the owner role is seeded here (P05.02 needs it). The remaining baseline
        // roles are added by migration 2026_10_04_000008_create_baseline_roles.php.
        $now = now('UTC');
        DB::table('roles')->insertOrIgnore([
            'id' => (string) Str::uuid7(),
            'key' => 'owner',
            'name' => 'Owner',
            'description' => null,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('staff_sessions');
        Schema::dropIfExists('staff_role_grants');
        Schema::dropIfExists('staff_users');
        Schema::dropIfExists('roles');
    }
};
