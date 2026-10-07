<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('print_bridges', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('name', 100);
            $table->char('token_hash', 64)->unique();
            $table->boolean('active')->default(true);
            $table->timestamp('last_seen_at')->nullable();
            $table->timestamps();
        });
        Schema::create('print_bridge_destinations', function (Blueprint $table): void {
            $table->uuid('bridge_id');
            $table->uuid('printer_destination_id');
            $table->primary(['bridge_id', 'printer_destination_id']);
            $table->foreign('bridge_id')->references('id')->on('print_bridges')->cascadeOnDelete();
            $table->foreign('printer_destination_id')->references('id')->on('printer_destinations')->cascadeOnDelete();
        });
        Schema::table('print_jobs', function (Blueprint $table): void {
            $table->uuid('leased_by_bridge_id')->nullable()->after('lease_token_hash');
            $table->foreign('leased_by_bridge_id')->references('id')->on('print_bridges')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('print_jobs', function (Blueprint $table): void {
            $table->dropForeign(['leased_by_bridge_id']);
            $table->dropColumn('leased_by_bridge_id');
        });
        Schema::dropIfExists('print_bridge_destinations');
        Schema::dropIfExists('print_bridges');
    }
};
