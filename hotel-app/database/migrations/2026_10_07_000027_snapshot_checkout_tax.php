<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Receipts must show the tax that applied when the bill was paid, not the
 * rate configured today. Paid checkouts carry a snapshot of the rate, label,
 * included tax and business date.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('checkouts', function (Blueprint $table): void {
            $table->unsignedSmallInteger('tax_rate_basis_points')->nullable();
            $table->string('tax_label', 40)->nullable();
            $table->unsignedBigInteger('tax_minor')->nullable();
            $table->date('business_date')->nullable();
            $table->index(['state', 'business_date']);
        });
    }

    public function down(): void
    {
        Schema::table('checkouts', function (Blueprint $table): void {
            $table->dropIndex(['state', 'business_date']);
            $table->dropColumn(['tax_rate_basis_points', 'tax_label', 'tax_minor', 'business_date']);
        });
    }
};
