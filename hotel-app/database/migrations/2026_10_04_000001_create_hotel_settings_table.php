<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hotel_settings', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            // Every row has the same generated key, so even concurrent inserts
            // cannot create a second installation identity in this database.
            $table->unsignedTinyInteger('installation_slot')->storedAs('1')->unique();
            $table->string('name', 150);
            $table->string('timezone', 64);
            $table->enum('currency', ['KES'])->default('KES');
            $table->time('business_day_cutoff')->nullable();
            $table->unsignedInteger('fiscal_configuration_version')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hotel_settings');
    }
};
