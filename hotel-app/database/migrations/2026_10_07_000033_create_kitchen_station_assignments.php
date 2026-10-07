<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('kitchen_station_assignments', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('station_id');
            $table->uuid('staff_user_id')->nullable();
            $table->uuid('device_id')->nullable();
            $table->timestamps();
            $table->unique(['staff_user_id', 'station_id']);
            $table->unique(['device_id', 'station_id']);
            $table->foreign('station_id')->references('id')->on('stations')->cascadeOnDelete();
            $table->foreign('staff_user_id')->references('id')->on('staff_users')->cascadeOnDelete();
            $table->foreign('device_id')->references('id')->on('devices')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kitchen_station_assignments');
    }
};
