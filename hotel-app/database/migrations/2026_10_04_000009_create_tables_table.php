<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tables', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('label', 64);
            $table->boolean('active')->default(true);
            // Unique among active tables only; inactive rows keep a NULL marker.
            $table->string('active_label', 64)->nullable()->storedAs('CASE WHEN active = 1 THEN label ELSE NULL END');
            $table->timestamps();
            $table->unique('active_label');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tables');
    }
};
