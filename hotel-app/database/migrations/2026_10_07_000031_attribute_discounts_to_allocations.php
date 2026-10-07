<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('adjustments', function (Blueprint $table): void {
            $table->uuid('allocation_id')->nullable()->after('charge_id');
            $table->index(['allocation_id', 'kind'], 'adjustments_allocation_kind_idx');
            $table->foreign('allocation_id', 'adjustments_allocation_fk')->references('id')->on('charge_allocations')->restrictOnDelete();
        });
        // Historical discounts cannot be assigned safely after sharing because
        // the old rows recorded only a charge. Leave null as a visible legacy
        // exception instead of guessing a beneficiary.
    }

    public function down(): void
    {
        Schema::table('adjustments', function (Blueprint $table): void {
            $table->dropForeign('adjustments_allocation_fk');
            $table->dropIndex('adjustments_allocation_kind_idx');
            $table->dropColumn('allocation_id');
        });
    }
};
