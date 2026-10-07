<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * A cancellation adjustment proves the charge was posted before cancellation.
 * Older code changed those charges to voided, which made reports either include
 * never-posted voids or double-subtract posted cancellations. Restore the
 * immutable posted-sale fact; the adjustment remains the reversal ledger row.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('charges')
            ->where('state', 'voided')
            ->whereIn('id', DB::table('adjustments')->select('charge_id')->where('kind', 'cancellation'))
            ->update(['state' => 'posted']);
    }

    public function down(): void
    {
        // Deliberately irreversible: posted is the historical fact and changing
        // it back would corrupt reports produced by either application version.
    }
};
