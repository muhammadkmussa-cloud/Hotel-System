<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('guests', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('visit_id');
            // Stable seat number inside the visit; label is the visible form.
            $table->unsignedInteger('display_number');
            $table->string('label', 32);
            // Optional: table ordering does not require a guest name.
            $table->string('name', 150)->nullable();
            $table->enum('state', ['active', 'settled'])->default('active');
            $table->unsignedInteger('version')->default(1);
            $table->timestamps();
            // Guest numbers and labels are unique inside one visit, never global.
            $table->unique(['visit_id', 'display_number'], 'guests_visit_number_unique');
            $table->unique(['visit_id', 'label'], 'guests_visit_label_unique');
            $table->index(['visit_id', 'state'], 'guests_visit_state_index');
            $table->foreign('visit_id')->references('id')->on('visits')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('guests');
    }
};
