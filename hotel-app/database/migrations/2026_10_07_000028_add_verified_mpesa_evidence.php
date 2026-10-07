<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Preserve the merchant/currency expected when an STK request is created and
 * the amount/receipt delivered by the authenticated callback. A successful
 * status query is not enough to settle a checkout without matching evidence.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payment_attempts', function (Blueprint $table): void {
            $table->string('merchant_reference', 80)->nullable()->after('environment');
            $table->char('currency', 3)->default('KES')->after('merchant_reference');
            $table->unsignedBigInteger('callback_amount_minor')->nullable()->after('provider_receipt');
            $table->string('callback_receipt', 40)->nullable()->after('callback_amount_minor');
            $table->timestamp('callback_received_at')->nullable()->after('callback_receipt');
        });

        Schema::create('mpesa_callback_inbox', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->char('payload_hash', 64)->unique();
            $table->string('checkout_request_id', 80);
            $table->unsignedBigInteger('amount_minor')->nullable();
            $table->string('receipt', 40)->nullable();
            $table->enum('state', ['pending', 'processed'])->default('pending');
            $table->unsignedSmallInteger('attempts')->default(0);
            $table->string('last_error', 200)->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();
            $table->index(['state', 'created_at']);
            $table->index('checkout_request_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mpesa_callback_inbox');
        Schema::table('payment_attempts', function (Blueprint $table): void {
            $table->dropColumn([
                'merchant_reference',
                'currency',
                'callback_amount_minor',
                'callback_receipt',
                'callback_received_at',
            ]);
        });
    }
};
