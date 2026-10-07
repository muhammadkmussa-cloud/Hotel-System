<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * P13.01 — immutable ingredient identity on each order item.
 *
 * The original `removed`/`extras` columns hold display names for the kitchen
 * and receipts. These columns additionally snapshot the stable ingredient IDs
 * (and extra unit prices) so a later rename or reprice cannot change what the
 * guest actually ordered or removed.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('order_items', function (Blueprint $table): void {
            $table->json('removed_ids')->nullable()->after('removed');
            $table->json('extras_snapshot')->nullable()->after('extras');
        });
    }

    public function down(): void
    {
        Schema::table('order_items', function (Blueprint $table): void {
            $table->dropColumn(['removed_ids', 'extras_snapshot']);
        });
    }
};
