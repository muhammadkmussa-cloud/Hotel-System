<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('idempotent_commands', function (Blueprint $table): void {
            $table->char('identity_hash', 64)->when(\Illuminate\Support\Facades\Schema::getConnection()->getDriverName() === 'mysql', fn ($column) => $column->charset('ascii')->collation('ascii_bin'))->primary();
            $table->char('body_hash', 64)->when(\Illuminate\Support\Facades\Schema::getConnection()->getDriverName() === 'mysql', fn ($column) => $column->charset('ascii')->collation('ascii_bin'));
            $table->unsignedSmallInteger('result_status')->nullable();
            $table->mediumText('result_data')->nullable();
            $table->timestamp('created_at');
        });
    }

    public function down(): void { Schema::dropIfExists('idempotent_commands'); }
};
