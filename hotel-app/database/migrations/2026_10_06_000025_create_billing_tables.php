<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * P18–P23 — charges, allocations, shares, checkouts, payment attempts,
 * payments, adjustments, refunds, cash custody and drawers.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('charges', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('order_item_id')->unique();
            $table->uuid('submission_id');
            $table->unsignedBigInteger('gross_minor');
            // proposed = provisional demand (review hold / unpaid kiosk);
            // posted = sale; voided = cancelled before payment.
            $table->enum('state', ['proposed', 'posted', 'voided']);
            $table->timestamp('posted_at')->nullable();
            $table->date('business_date')->nullable();
            $table->timestamps();
            $table->index(['state', 'business_date']);
            $table->foreign('order_item_id')->references('id')->on('order_items')->restrictOnDelete();
            $table->foreign('submission_id')->references('id')->on('order_submissions')->restrictOnDelete();
        });

        Schema::create('charge_allocations', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('charge_id');
            $table->uuid('guest_id')->nullable();
            $table->uuid('kiosk_order_id')->nullable();
            $table->unsignedBigInteger('amount_minor');
            // open → frozen (in an active checkout) → paid. voided rows are
            // superseded history (shares, discounts, cancellations).
            $table->enum('state', ['open', 'frozen', 'paid', 'voided']);
            $table->uuid('checkout_id')->nullable();
            $table->string('reason', 40)->default('ordered');
            $table->timestamps();
            $table->index(['guest_id', 'state']);
            $table->index(['kiosk_order_id', 'state']);
            $table->index(['charge_id', 'state']);
            $table->foreign('charge_id')->references('id')->on('charges')->restrictOnDelete();
        });

        Schema::create('share_proposals', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('charge_id');
            $table->json('guest_ids');
            $table->uuid('proposed_by_guest_id')->nullable();
            $table->uuid('proposed_by_staff_user_id')->nullable();
            $table->enum('state', ['pending', 'confirmed', 'rejected']);
            $table->uuid('decided_by')->nullable();
            $table->timestamp('decided_at')->nullable();
            $table->timestamps();
            $table->index(['charge_id', 'state']);
            $table->foreign('charge_id')->references('id')->on('charges')->restrictOnDelete();
        });

        Schema::create('checkouts', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('guest_id')->nullable();
            $table->uuid('kiosk_order_id')->nullable();
            $table->unsignedBigInteger('amount_minor');
            $table->unsignedBigInteger('paid_minor')->default(0);
            $table->enum('state', ['open', 'paid', 'cancelled']);
            $table->string('receipt_number', 32)->nullable()->unique();
            $table->uuid('created_by_staff_user_id')->nullable();
            $table->uuid('created_by_device_session_id')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->unsignedInteger('version')->default(1);
            $table->timestamps();
            // One active checkout per bill owner (MySQL-compatible partial unique).
            $table->string('active_owner', 40)->nullable()->storedAs("CASE WHEN state = 'open' THEN COALESCE(guest_id, kiosk_order_id) ELSE NULL END");
            $table->unique('active_owner', 'checkouts_active_owner_unique');
            $table->index(['state', 'created_at']);
        });

        Schema::create('payment_attempts', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('checkout_id');
            $table->enum('method', ['mpesa']);
            $table->enum('environment', ['simulator', 'sandbox', 'production']);
            $table->unsignedBigInteger('amount_minor');
            $table->string('phone_masked', 20);
            $table->char('phone_hash', 64);
            $table->enum('state', ['pending', 'succeeded', 'failed', 'cancelled', 'unknown']);
            $table->string('merchant_request_id', 80)->nullable();
            $table->string('checkout_request_id', 80)->nullable()->unique();
            $table->string('provider_receipt', 40)->nullable();
            $table->string('result_code', 16)->nullable();
            $table->string('result_desc', 200)->nullable();
            $table->unsignedSmallInteger('query_attempts')->default(0);
            $table->timestamp('next_query_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
            $table->string('active_checkout', 40)->nullable()->storedAs("CASE WHEN state = 'pending' THEN checkout_id ELSE NULL END");
            $table->unique('active_checkout', 'payment_attempts_one_live_unique');
            $table->index(['state', 'next_query_at']);
            $table->foreign('checkout_id')->references('id')->on('checkouts')->restrictOnDelete();
        });

        Schema::create('drawer_sessions', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('opened_by');
            $table->unsignedBigInteger('opening_float_minor');
            $table->enum('state', ['open', 'closed']);
            $table->uuid('closed_by')->nullable();
            $table->unsignedBigInteger('expected_minor')->nullable();
            $table->unsignedBigInteger('counted_minor')->nullable();
            $table->bigInteger('variance_minor')->nullable();
            $table->string('note', 300)->nullable();
            $table->timestamp('opened_at');
            $table->timestamp('closed_at')->nullable();
            $table->timestamps();
            $table->unsignedTinyInteger('open_flag')->nullable()->storedAs("CASE WHEN state = 'open' THEN 1 ELSE NULL END");
            $table->unique('open_flag', 'drawer_sessions_one_open_unique');
        });

        Schema::create('payments', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('checkout_id');
            $table->enum('method', ['cash', 'card', 'mpesa']);
            $table->unsignedBigInteger('amount_minor');
            $table->unsignedBigInteger('tendered_minor')->nullable();
            $table->unsignedBigInteger('change_minor')->nullable();
            $table->string('reference', 64)->nullable();
            // applied = settles the checkout; unapplied = money received that
            // could not be applied (late kiosk success) and awaits staff.
            $table->enum('state', ['applied', 'unapplied']);
            $table->uuid('payment_attempt_id')->nullable()->unique();
            $table->uuid('recorded_by_staff_user_id')->nullable();
            // Cash custody: who physically holds this cash right now.
            $table->uuid('custody_staff_user_id')->nullable();
            $table->uuid('drawer_session_id')->nullable();
            $table->unsignedBigInteger('refunded_minor')->default(0);
            $table->date('business_date')->nullable();
            $table->timestamps();
            $table->unique(['method', 'reference'], 'payments_method_reference_unique');
            $table->index(['business_date', 'method']);
            $table->foreign('checkout_id')->references('id')->on('checkouts')->restrictOnDelete();
        });

        Schema::create('adjustments', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('charge_id');
            $table->enum('kind', ['discount', 'cancellation']);
            $table->unsignedBigInteger('amount_minor');
            $table->string('reason', 300);
            $table->uuid('approved_by');
            $table->date('business_date')->nullable();
            $table->timestamp('created_at');
            $table->index('charge_id');
            $table->foreign('charge_id')->references('id')->on('charges')->restrictOnDelete();
        });

        Schema::create('refunds', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('payment_id');
            $table->unsignedBigInteger('amount_minor');
            $table->string('reason', 300);
            $table->enum('state', ['requested', 'approved', 'completed', 'rejected']);
            $table->uuid('requested_by');
            $table->uuid('approved_by')->nullable();
            $table->uuid('completed_by')->nullable();
            $table->string('external_reference', 64)->nullable();
            $table->date('business_date')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
            $table->index(['state', 'created_at']);
            $table->foreign('payment_id')->references('id')->on('payments')->restrictOnDelete();
        });

        Schema::create('cash_handovers', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('waiter_id');
            $table->unsignedBigInteger('declared_minor');
            $table->unsignedBigInteger('counted_minor')->nullable();
            $table->bigInteger('difference_minor')->nullable();
            $table->enum('state', ['proposed', 'accepted', 'rejected']);
            $table->uuid('cashier_id')->nullable();
            $table->uuid('drawer_session_id')->nullable();
            $table->string('note', 300)->nullable();
            $table->timestamp('accepted_at')->nullable();
            $table->timestamps();
            $table->index(['waiter_id', 'state']);
        });
    }

    public function down(): void
    {
        foreach (['cash_handovers', 'refunds', 'adjustments', 'payments', 'drawer_sessions', 'payment_attempts', 'checkouts', 'share_proposals', 'charge_allocations', 'charges'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
