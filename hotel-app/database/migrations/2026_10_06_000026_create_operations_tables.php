<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * P15/P17/P25 — durable outbox events for polling, background jobs with
 * leases, print jobs, fiscal documents and heartbeats. Also adds the hotel's
 * tax configuration reference and integrations settings.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('outbox_events', function (Blueprint $table): void {
            $table->id(); // monotonic cursor
            $table->string('topic', 64);
            $table->string('scope', 80);
            $table->json('payload');
            $table->timestamp('created_at');
            $table->index(['scope', 'id']);
        });

        Schema::create('background_jobs', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('type', 48);
            $table->string('unique_key', 160)->unique();
            $table->json('payload');
            $table->enum('state', ['pending', 'leased', 'done', 'failed']);
            $table->unsignedSmallInteger('attempts')->default(0);
            $table->unsignedSmallInteger('max_attempts')->default(8);
            $table->timestamp('available_at');
            $table->timestamp('lease_expires_at')->nullable();
            $table->string('lease_owner', 64)->nullable();
            $table->string('last_error', 300)->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
            $table->index(['state', 'available_at']);
        });

        Schema::create('print_jobs', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->enum('kind', ['kitchen_ticket', 'receipt', 'unpaid_reference', 'bill']);
            $table->string('source_type', 32);
            $table->uuid('source_id');
            $table->uuid('printer_destination_id')->nullable();
            $table->text('payload');
            $table->boolean('is_copy')->default(false);
            $table->uuid('copy_of_id')->nullable();
            $table->string('dedupe_key', 120)->nullable()->unique();
            $table->enum('state', ['queued', 'leased', 'sent', 'failed', 'unknown']);
            $table->char('lease_token_hash', 64)->nullable();
            $table->timestamp('lease_expires_at')->nullable();
            $table->unsignedSmallInteger('attempts')->default(0);
            $table->string('last_error', 300)->nullable();
            $table->boolean('simulated')->default(false);
            $table->uuid('requested_by')->nullable();
            $table->timestamp('reported_at')->nullable();
            $table->timestamps();
            $table->index(['state', 'created_at']);
            $table->index(['source_type', 'source_id']);
        });

        Schema::create('fiscal_documents', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->enum('kind', ['invoice', 'credit_note']);
            $table->uuid('checkout_id')->nullable();
            $table->uuid('refund_id')->nullable();
            $table->uuid('related_document_id')->nullable();
            $table->string('request_reference', 64)->unique();
            $table->json('payload');
            $table->unsignedBigInteger('total_minor');
            $table->enum('state', ['pending', 'submitted', 'accepted', 'failed', 'uncertain']);
            $table->string('provider', 32);
            $table->string('provider_reference', 80)->nullable();
            $table->string('provider_document_number', 80)->nullable();
            $table->unsignedSmallInteger('attempts')->default(0);
            $table->string('last_error', 300)->nullable();
            $table->timestamps();
            $table->unique(['kind', 'checkout_id'], 'fiscal_documents_invoice_per_checkout');
            $table->unique(['kind', 'refund_id'], 'fiscal_documents_credit_per_refund');
            $table->index(['state', 'updated_at']);
        });

        Schema::create('system_heartbeats', function (Blueprint $table): void {
            $table->string('name', 64)->primary();
            $table->timestamp('last_seen_at');
            $table->json('detail')->nullable();
        });

        Schema::table('stations', function (Blueprint $table): void {
            $table->uuid('printer_destination_id')->nullable();
        });

        Schema::table('hotel_settings', function (Blueprint $table): void {
            // Basis points of tax already included in menu prices; null means
            // the hotel has not approved a tax configuration (no invented rule).
            $table->unsignedSmallInteger('tax_rate_basis_points')->nullable();
            $table->string('tax_label', 40)->nullable();
            $table->string('kra_pin', 20)->nullable();
            $table->unsignedSmallInteger('kiosk_payment_minutes')->default(10);
            $table->unsignedInteger('next_receipt_number')->default(1);
        });
    }

    public function down(): void
    {
        Schema::table('hotel_settings', function (Blueprint $table): void {
            $table->dropColumn(['tax_rate_basis_points', 'tax_label', 'kra_pin', 'kiosk_payment_minutes', 'next_receipt_number']);
        });
        Schema::table('stations', function (Blueprint $table): void {
            $table->dropColumn('printer_destination_id');
        });
        foreach (['system_heartbeats', 'fiscal_documents', 'print_jobs', 'background_jobs', 'outbox_events'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
