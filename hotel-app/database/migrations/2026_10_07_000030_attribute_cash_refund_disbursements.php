<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** Attribute every new cash refund to the account that physically paid it. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('refunds', function (Blueprint $table): void {
            $table->enum('cash_source_type', ['drawer', 'custodian', 'unattributed'])->nullable()->after('external_reference');
            $table->uuid('cash_drawer_session_id')->nullable()->after('cash_source_type');
            $table->uuid('cash_custody_staff_user_id')->nullable()->after('cash_drawer_session_id');
            $table->uuid('cash_handover_id')->nullable()->after('cash_custody_staff_user_id');
            $table->index(['cash_drawer_session_id', 'state'], 'refunds_cash_drawer_state_idx');
            $table->index(['cash_custody_staff_user_id', 'state', 'cash_handover_id'], 'refunds_cash_custody_state_idx');
            $table->foreign('cash_drawer_session_id', 'refunds_cash_drawer_fk')->references('id')->on('drawer_sessions')->restrictOnDelete();
            $table->foreign('cash_custody_staff_user_id', 'refunds_cash_custodian_fk')->references('id')->on('staff_users')->restrictOnDelete();
            $table->foreign('cash_handover_id', 'refunds_cash_handover_fk')->references('id')->on('cash_handovers')->restrictOnDelete();
        });

        // The actual source of historical cash payouts cannot be inferred from
        // the original payment. Keep them visible for manual reconciliation.
        DB::table('refunds')->where('state', 'completed')
            ->whereIn('payment_id', DB::table('payments')->select('id')->where('method', 'cash'))
            ->update(['cash_source_type' => 'unattributed']);
    }

    public function down(): void
    {
        Schema::table('refunds', function (Blueprint $table): void {
            $table->dropForeign('refunds_cash_drawer_fk');
            $table->dropForeign('refunds_cash_custodian_fk');
            $table->dropForeign('refunds_cash_handover_fk');
            $table->dropIndex('refunds_cash_drawer_state_idx');
            $table->dropIndex('refunds_cash_custody_state_idx');
            $table->dropColumn(['cash_source_type', 'cash_drawer_session_id', 'cash_custody_staff_user_id', 'cash_handover_id']);
        });
    }
};
