<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('hotel_settings', function (Blueprint $table): void {
            $table->string('receipt_header', 150)->nullable();
            $table->string('receipt_footer', 150)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('hotel_settings', fn (Blueprint $table) => $table->dropColumn(['receipt_header', 'receipt_footer']));
    }
};
