<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * P07.09 — the waiter who owns a visit. Transfers reassign this column;
     * guest and order identity never changes with it.
     */
    public function up(): void
    {
        if (! Schema::hasColumn('visits', 'owner_waiter_id')) {
            Schema::table('visits', function (Blueprint $table): void {
                $table->uuid('owner_waiter_id')->nullable()->after('table_id');
                $table->index('owner_waiter_id', 'visits_owner_waiter_index');
                $table->foreign('owner_waiter_id')->references('id')->on('staff_users')->restrictOnDelete();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('visits', 'owner_waiter_id')) {
            Schema::table('visits', function (Blueprint $table): void {
                $table->dropForeign(['owner_waiter_id']);
                $table->dropIndex('visits_owner_waiter_index');
                $table->dropColumn('owner_waiter_id');
            });
        }
    }
};
