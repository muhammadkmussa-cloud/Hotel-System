<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('installation_bootstrap', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            // One generated value for every row, so concurrent bootstraps cannot
            // both succeed: the second unique insert is rejected by the database.
            $table->unsignedTinyInteger('installation_slot')->storedAs('1')->unique();
            $table->uuid('owner_staff_user_id');
            $table->timestamp('bootstrapped_at');
            $table->timestamps();
            $table->foreign('owner_staff_user_id')->references('id')->on('staff_users')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('installation_bootstrap');
    }
};
