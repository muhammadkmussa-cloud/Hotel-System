<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * P12–P16/P24 — draft carts, kiosk orders, immutable submissions and items,
 * review requests, kitchen tickets and portion reservations.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('kiosk_orders', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('device_session_id');
            $table->enum('dining', ['eat_in', 'takeaway'])->default('takeaway');
            $table->string('collection_name', 40)->nullable();
            $table->enum('state', ['draft', 'review_hold', 'pending_payment', 'paid', 'released', 'collected', 'cancelled', 'expired', 'declined'])->default('draft');
            $table->enum('payment_route', ['mpesa', 'cashier'])->nullable();
            $table->string('reference_code', 12)->nullable()->unique();
            $table->date('business_date')->nullable();
            $table->unsignedInteger('collection_number')->nullable();
            $table->unsignedBigInteger('total_minor')->default(0);
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('released_at')->nullable();
            $table->timestamp('collected_at')->nullable();
            $table->timestamp('ended_at')->nullable();
            $table->unsignedInteger('version')->default(1);
            $table->timestamps();
            $table->unique(['business_date', 'collection_number'], 'kiosk_orders_collection_unique');
            $table->index(['state', 'expires_at']);
            $table->foreign('device_session_id')->references('id')->on('device_sessions')->restrictOnDelete();
        });

        Schema::create('cart_lines', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            // Guest binding id or kiosk order id. A new binding never sees an
            // older binding's draft.
            $table->string('owner_type', 16);
            $table->uuid('owner_id');
            $table->uuid('meal_id');
            $table->unsignedInteger('meal_version');
            $table->json('removed_ingredient_ids');
            $table->json('extra_ingredient_ids');
            $table->unsignedSmallInteger('quantity');
            $table->string('note', 200)->nullable();
            $table->timestamp('expires_at');
            $table->timestamps();
            $table->index(['owner_type', 'owner_id']);
            $table->foreign('meal_id')->references('id')->on('meals')->cascadeOnDelete();
        });

        Schema::create('order_submissions', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->enum('channel', ['table', 'kiosk']);
            $table->uuid('visit_id')->nullable();
            $table->uuid('guest_id')->nullable();
            $table->uuid('kiosk_order_id')->nullable();
            $table->string('table_label', 64)->nullable();
            $table->string('guest_label', 32)->nullable();
            $table->string('reference', 32);
            $table->enum('state', ['review_hold', 'awaiting_payment', 'released', 'declined', 'cancelled']);
            $table->char('idempotency_hash', 64)->unique();
            $table->char('quote_digest', 64);
            $table->text('allergy_note')->nullable();
            $table->enum('review_state', ['none', 'pending', 'approved', 'declined'])->default('none');
            $table->uuid('reviewed_by')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->string('review_note', 500)->nullable();
            $table->unsignedBigInteger('total_minor');
            $table->uuid('device_session_id')->nullable();
            $table->timestamp('released_at')->nullable();
            $table->unsignedInteger('version')->default(1);
            $table->timestamps();
            $table->index(['visit_id', 'created_at']);
            $table->index(['guest_id', 'created_at']);
            $table->index(['state', 'created_at']);
            $table->foreign('visit_id')->references('id')->on('visits')->restrictOnDelete();
            $table->foreign('guest_id')->references('id')->on('guests')->restrictOnDelete();
            $table->foreign('kiosk_order_id')->references('id')->on('kiosk_orders')->restrictOnDelete();
        });

        Schema::create('kitchen_tickets', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('submission_id');
            $table->uuid('station_id')->nullable();
            $table->string('station_name', 64)->nullable();
            $table->enum('state', ['new', 'acknowledged', 'preparing', 'ready', 'served', 'cancelled'])->default('new');
            $table->timestamp('acknowledged_at')->nullable();
            $table->timestamp('preparing_at')->nullable();
            $table->timestamp('ready_at')->nullable();
            $table->timestamp('served_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->string('cancel_reason', 200)->nullable();
            $table->unsignedInteger('version')->default(1);
            $table->timestamps();
            $table->unique(['submission_id', 'station_id'], 'kitchen_tickets_submission_station_unique');
            $table->index(['state', 'created_at']);
            $table->foreign('submission_id')->references('id')->on('order_submissions')->restrictOnDelete();
        });

        Schema::create('order_items', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('submission_id');
            $table->uuid('ticket_id')->nullable();
            $table->uuid('meal_id');
            $table->unsignedInteger('meal_version');
            $table->string('meal_name', 120);
            $table->unsignedBigInteger('unit_price_minor');
            $table->unsignedBigInteger('extras_minor')->default(0);
            $table->unsignedSmallInteger('quantity');
            $table->unsignedBigInteger('line_total_minor');
            $table->json('removed');
            $table->json('extras');
            $table->string('note', 200)->nullable();
            $table->uuid('station_id')->nullable();
            $table->boolean('cancelled')->default(false);
            $table->string('cancel_reason', 200)->nullable();
            $table->timestamps();
            $table->index('submission_id');
            $table->foreign('submission_id')->references('id')->on('order_submissions')->restrictOnDelete();
            $table->foreign('ticket_id')->references('id')->on('kitchen_tickets')->restrictOnDelete();
        });

        Schema::create('portion_reservations', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('meal_id');
            $table->uuid('submission_id');
            $table->unsignedSmallInteger('quantity');
            $table->enum('state', ['reserved', 'consumed', 'released']);
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();
            $table->index(['state', 'expires_at']);
            $table->foreign('submission_id')->references('id')->on('order_submissions')->restrictOnDelete();
        });

        Schema::create('service_requests', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('visit_id');
            $table->uuid('guest_id')->nullable();
            $table->enum('kind', ['call_waiter', 'bill_help', 'change_request']);
            $table->string('note', 300)->nullable();
            $table->uuid('submission_id')->nullable();
            $table->enum('state', ['open', 'acknowledged', 'resolved'])->default('open');
            $table->uuid('acknowledged_by')->nullable();
            $table->uuid('resolved_by')->nullable();
            $table->string('resolution', 300)->nullable();
            $table->timestamps();
            $table->index(['state', 'created_at']);
            $table->foreign('visit_id')->references('id')->on('visits')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        foreach (['service_requests', 'portion_reservations', 'order_items', 'kitchen_tickets', 'order_submissions', 'cart_lines', 'kiosk_orders'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
